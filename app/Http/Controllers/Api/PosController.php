<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\StockMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PosController extends Controller
{
    /**
     * Store a new transaction from the POS.
     */
    public function store(Request $request)
    {
        // Validasi data yang HANYA bisa datang dari frontend
        $validated = $request->validate([
            'shift_id' => 'required|exists:shifts,id', // Shift ID wajib ada
            'customer_id' => 'nullable|exists:customers,id',
            'payment_method' => 'required|string',
            'amount_paid' => 'required|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'outstanding_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'change_given' => 'nullable|numeric',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.discount_amount' => 'sometimes|numeric|min:0',
            'bank_transaction_ref' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'is_credit_sale' => 'sometimes|boolean',
            'credit_due_date' => 'nullable|date',
        ]);

        $user = auth()->user();
        $branchId = $user->branch_id;

        if (! $branchId) {
            return response()->json(['message' => 'User does not belong to any branch.'], 422);
        }

        DB::beginTransaction();

        try {
            $totalCost = 0;
            $subtotal = 0;
            $totalItemDiscount = 0;

            // 1. Proses setiap item untuk kalkulasi dan validasi stok
            foreach ($validated['items'] as $itemData) {
                $product = Product::find($itemData['product_id']);

                // Validasi stok
                if ($product->quantity < $itemData['quantity']) {
                    throw new \Exception("Insufficient stock for product: {$product->name}");
                }

                $itemSubtotal = $product->price * $itemData['quantity'];
                $itemDiscount = $itemData['discount_amount'] ?? 0;

                $totalCost += $product->cost_price * $itemData['quantity'];
                $subtotal += $itemSubtotal;
                $totalItemDiscount += $itemDiscount;
            }

            // Anda bisa tambahkan logika Tax dan Shipping Cost di sini jika perlu
            $taxAmount = $validated['tax_amount']; // Contoh: ($subtotal - $totalItemDiscount) * 0.11;
            $shippingCost = $validated['shipping_cost'] ?? 0;

            $totalAmount = ($subtotal - $totalItemDiscount) + $taxAmount + $shippingCost;

            // Tentukan status pembayaran & outstanding untuk kredit
            $isCredit = (bool) ($validated['is_credit_sale'] ?? false);
            $amountPaid = (float) ($validated['amount_paid'] ?? 0);
            $outstanding = $isCredit ? max(0, $totalAmount - $amountPaid) : 0;
            $paymentStatus = $isCredit
                ? ($outstanding <= 0 ? 'paid' : ($amountPaid > 0 ? 'partially_paid' : 'unpaid'))
                : 'paid';

            // 2. Buat record Sale (header transaksi)
            $sale = Sale::create([
                'transaction_number' => 'TRX-'.date('Ymd').'-'.strtoupper(Str::random(6)),
                'status' => 'completed',
                'user_id' => $user->id, // Diambil dari backend
                'branch_id' => $branchId, // Diambil dari backend
                'shift_id' => $validated['shift_id'], // Diambil dari frontend
                'customer_id' => $validated['customer_id'] ?? null,
                'user_name' => $user->name, // Diambil dari backend
                'customer_name' => \App\Models\Customer::find($validated['customer_id'])->name ?? 'Walk-in Customer',
                'subtotal' => $subtotal,
                'total_discount_amount' => $totalItemDiscount,
                'tax_amount' => $taxAmount,
                'shipping_cost' => $shippingCost,
                'total_amount' => $totalAmount,
                'total_cogs' => $totalCost,
                'payment_method' => $validated['payment_method'],
                'payment_status' => $paymentStatus,
                'amount_paid' => $amountPaid,
                'change_given' => $isCredit ? 0 : ($validated['change_given'] ?? 0),
                'notes' => $validated['notes'] ?? null,
                'bank_transaction_ref' => $validated['bank_transaction_ref'] ?? null,
                'is_credit_sale' => $isCredit,
                'credit_due_date' => $isCredit ? ($validated['credit_due_date'] ?? null) : null,
                'outstanding_amount' => $outstanding,
            ]);

            // 3. Buat record SaleDetail untuk setiap item & kurangi stok
            foreach ($validated['items'] as $itemData) {
                $product = Product::find($itemData['product_id']);
                $itemSubtotal = $product->price * $itemData['quantity'];

                $saleDetail = SaleDetail::create([
                    'sale_id' => $sale->id,
                    'branch_id' => $branchId, // Diambil dari user yang login
                    'product_id' => $product->id,
                    'product_name' => $product->name, // Simpan nama produk saat ini
                    'branch_name' => $sale->branch->name, // Simpan nama cabang saat ini
                    'sku' => $product->sku ?? '-',
                    'quantity' => $itemData['quantity'],
                    'price_at_sale' => $product->price, // Simpan harga jual saat transaksi
                    'cost_at_sale' => $product->cost_price, // Simpan harga beli saat transaksi
                    'discount_amount' => $itemData['discount_amount'] ?? 0,
                    'subtotal' => $itemSubtotal,
                    'category_id' => $product->category_id,
                ]);

                $stockBefore = $product->quantity;
                $quantityChange = -$itemData['quantity']; // Negatif karena penjualan

                // Kurangi stok produk
                $product->decrement('quantity', $itemData['quantity']);

                // Catat mutasi stok
                StockMutation::create([
                    'branch_id' => $branchId,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity_change' => $quantityChange,
                    'stock_before' => $stockBefore,
                    'stock_after' => $product->fresh()->quantity, // Ambil stok terbaru
                    'type' => 'sale',
                    'description' => "Penjualan via POS #{$sale->transaction_number}",
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                ]);
            }

            DB::commit();

            return response()->json($sale->load('saleDetails'), 201);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('POS Transaction Error: '.$e->getMessage());
            Log::error($e->getTraceAsString()); // Untuk debug lebih detail

            return response()->json(['message' => 'Transaction Failed: '.$e->getMessage()], 500);
        }
    }
}
