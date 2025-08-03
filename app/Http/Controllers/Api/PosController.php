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
        // Validasi data header transaksi
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'branch_id' => 'required|exists:branches,id',
            'customer_id' => 'nullable|exists:customers,id',
            'payment_method' => 'required|string',
            'amount_paid' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            // Validasi setiap item di dalam keranjang
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.discount_amount' => 'sometimes|numeric|min:0',
        ]);

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
            $taxAmount = 0; // Contoh: ($subtotal - $totalItemDiscount) * 0.11;
            $shippingCost = 0;

            $totalAmount = ($subtotal - $totalItemDiscount) + $taxAmount + $shippingCost;
            $changeGiven = $validated['amount_paid'] - $totalAmount;

            if ($changeGiven < 0) {
                throw new \Exception("Insufficient payment amount.");
            }

            // 2. Buat record Sale (header transaksi)
            $sale = Sale::create([
                'transaction_number' => 'TRX-' . Str::uuid()->toString(),
                'status' => 'completed',
                'user_id' => $validated['user_id'],
                'branch_id' => $validated['branch_id'],
                'customer_id' => $validated['customer_id'] ?? null,
                'user_name' => auth()->user()->name, // Mengambil dari user yang login
                'customer_name' => \App\Models\Customer::find($validated['customer_id'])->name ?? null,
                'subtotal' => $subtotal,
                'total_discount_amount' => $totalItemDiscount, // Bisa ditambah diskon voucher
                'tax_amount' => $taxAmount,
                'shipping_cost' => $shippingCost,
                'total_amount' => $totalAmount,
                'total_cogs' => $totalCost,
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'paid',
                'amount_paid' => $validated['amount_paid'],
                'change_given' => $changeGiven,
                'notes' => $validated['notes'] ?? null,
            ]);

            // 3. Buat record SaleDetail untuk setiap item & kurangi stok
            foreach ($validated['items'] as $itemData) {
                $product = Product::find($itemData['product_id']);
                $itemSubtotal = $product->price * $itemData['quantity'];

                $saleDetail = SaleDetail::create([
                    'sale_id' => $sale->id,
                    // ... (field lainnya) ...
                ]);

                $stockBefore = $product->quantity;
                $quantityChange = -$itemData['quantity']; // Negatif karena penjualan

                // Kurangi stok produk
                $product->decrement('quantity', $itemData['quantity']);

                // Catat mutasi stok
                StockMutation::create([
                    'branch_id' => $validated['branch_id'],
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity_change' => $quantityChange,
                    'stock_before' => $stockBefore,
                    'stock_after' => $product->fresh()->quantity,
                    'type' => 'sale',
                    'description' => 'Sale Transaction',
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'user_id' => auth()->id(),
                    'user_name' => auth()->user()->name,
                ]);
            }

            DB::commit();

            return response()->json($sale->load('saleDetails'), 201);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error("POS Transaction Error: " . $e->getMessage());
            Log::error($e->getTraceAsString()); // Untuk debug lebih detail
            return response()->json(['message' => 'Transaction Failed: ' . $e->getMessage()], 500);
        }
    }
}
