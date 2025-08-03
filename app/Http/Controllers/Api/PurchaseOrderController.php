<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\StockMutation;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PurchaseOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $limit = $request->input('limit', 15);
            $search = $request->input('search');
            $status = $request->input('status');

            $query = PurchaseOrder::with(['supplier:id,name', 'branch:id,name']);

            if ($search) {
                $query->where('po_number', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%");
            }

            if ($status) {
                $query->where('status', $status);
            }

            $purchaseOrders = $query->latest()->paginate($limit);
            return response()->json($purchaseOrders);
        } catch (\Exception $e) {
            Log::error('Error fetching purchase orders: ' . $e->getMessage());
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'branch_id' => 'required|exists:branches,id',
            'order_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            // Sesuaikan dengan nama kolom di frontend/request
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.cost' => 'required|numeric|min:0',
        ]);

        try {
            $po = DB::transaction(function () use ($validated) {
                $supplier = Supplier::findOrFail($validated['supplier_id']);
                $subtotal = 0;

                foreach ($validated['items'] as $item) {
                    $subtotal += $item['cost'] * $item['quantity'];
                }

                $totalAmount = $subtotal; // Tambahkan logika tax/shipping jika perlu

                $purchaseOrder = PurchaseOrder::create([
                    'po_number' => 'PO-' . time(),
                    'branch_id' => $validated['branch_id'],
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                    'order_date' => Carbon::parse($validated['order_date']),
                    'user_id' => auth()->id(),
                    'status' => 'pending',
                    'payment_status' => 'unpaid',
                    'subtotal' => $subtotal,
                    'total_amount' => $totalAmount,
                    'outstanding_amount' => $totalAmount,
                    'notes' => $validated['notes'] ?? null,
                ]);

                foreach ($validated['items'] as $item) {
                    $product = Product::find($item['product_id']);
                    // Koreksi di sini: Gunakan nama kolom dari migrasi purchase_order_details
                    PurchaseOrderDetail::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'branch_id' => $validated['branch_id'],
                        'product_id' => $item['product_id'],
                        'product_name' => $product->name, // Ambil nama produk
                        'ordered_quantity' => $item['quantity'], // Diubah dari 'quantity'
                        'purchase_price' => $item['cost'],         // Diubah dari 'cost'
                        'total_price' => $item['cost'] * $item['quantity'], // Diubah dari 'subtotal'
                    ]);
                }

                return $purchaseOrder;
            });

            return response()->json($po->load('purchaseOrderDetails'), 201);
        } catch (\Exception $e) {
            Log::error('Error creating purchase order: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create Purchase Order.'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(PurchaseOrder $purchaseOrder)
    {
        return $purchaseOrder->load(['supplier', 'branch', 'user', 'purchaseOrderDetails.product']);
    }

    /**
     * Mark a purchase order as completed and update stock.
     */
    public function receiveOrder(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'pending') {
            return response()->json(['message' => 'This order cannot be received.'], 422);
        }

        try {
            DB::transaction(function () use ($purchaseOrder) {
                foreach ($purchaseOrder->purchaseOrderDetails as $detail) {
                    $product = Product::findOrFail($detail->product_id);

                    $stockBefore = $product->quantity;
                    $quantityChange = $detail->ordered_quantity; // Positif karena pembelian

                    // Tambah stok produk
                    $product->increment('quantity', $quantityChange);

                    // Catat mutasi stok
                    StockMutation::create([
                        'branch_id' => $purchaseOrder->branch_id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity_change' => $quantityChange,
                        'stock_before' => $stockBefore,
                        'stock_after' => $product->fresh()->quantity,
                        'type' => 'purchase',
                        'description' => 'Stock from PO ' . $purchaseOrder->po_number,
                        'reference_type' => PurchaseOrder::class,
                        'reference_id' => $purchaseOrder->id,
                        'user_id' => auth()->id(),
                        'user_name' => auth()->user()->name,
                    ]);
                }

                // Update status PO
                $purchaseOrder->update(['status' => 'completed']);
            });

            return response()->json(['message' => 'Order received and stock updated successfully.']);
        } catch (\Exception $e) {
            Log::error("Error receiving PO {$purchaseOrder->id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to receive order.'], 500);
        }
    }


    /**
     * Cancel a pending purchase order.
     */
    public function destroy(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'pending') {
            return response()->json(['message' => 'Only pending orders can be canceled.'], 422);
        }

        try {
            $purchaseOrder->delete(); // Menggunakan cascade delete dari migrasi
            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error("Error canceling PO {$purchaseOrder->id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to cancel order.'], 500);
        }
    }
}
