<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\StockMutation;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            $paymentStatus = $request->input('payment_status');

            $query = PurchaseOrder::with(['supplier:id,name', 'branch:id,name']);

            // Filter berdasarkan branch_id
            if ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            // [BARU] Filter untuk halaman Accounts Payable
            // Jika ada parameter has_outstanding=true, filter PO yang punya hutang
            if ($request->boolean('has_outstanding')) {
                $query->where('outstanding_amount', '>', 0);
            }

            if ($search) {
                $query->where('po_number', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%");
            }

            if ($status) {
                $query->where('status', $status);
            }

            // Optional: filter by payment_status (unpaid, partially_paid, paid)
            if ($paymentStatus) {
                $query->where('payment_status', $paymentStatus);
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
            'order_date' => 'required',
            'expected_delivery_date' => 'nullable',
            'payment_due_date' => 'nullable',
            'notes' => 'nullable|string',
            'is_credit' => 'sometimes|boolean',
            'payment_terms' => 'nullable|string|in:cash,credit',
            'supplier_invoice_number' => 'nullable|string|max:255',
            'tax_discount_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'shipping_cost_charged' => 'nullable|numeric|min:0',
            'other_costs' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.cost' => 'required|numeric|min:0',
            'status' => 'nullable|string',
        ]);

        try {
            $po = DB::transaction(function () use ($validated, $request) {
                $supplier = Supplier::findOrFail($validated['supplier_id']);

                $parseDate = function ($value) {
                    if (! $value) {
                        return null;
                    }
                    if (is_string($value) && strlen($value) === 10) {
                        return Carbon::createFromFormat('Y-m-d', $value, config('app.timezone'))->startOfDay();
                    }
                    try {
                        return Carbon::parse($value, config('app.timezone'))->startOfDay();
                    } catch (\Exception $e) {
                        return null;
                    }
                };

                $subtotal = 0;
                foreach ($validated['items'] as $item) {
                    $subtotal += $item['cost'] * $item['quantity'];
                }

                $discount = (float) ($validated['tax_discount_amount'] ?? 0);
                $shipping = (float) ($validated['shipping_cost_charged'] ?? 0);
                $tax = (float) ($validated['tax_amount'] ?? 0);
                $other = (float) ($validated['other_costs'] ?? 0);

                $discount = max(0, $discount);
                $shipping = max(0, $shipping);
                $tax = max(0, $tax);
                $other = max(0, $other);

                $taxableBase = max(0, $subtotal - $discount);
                $totalAmount = $taxableBase + $shipping + $tax + $other;

                $isCredit = (bool) ($validated['is_credit'] ?? ($validated['payment_terms'] ?? '') === 'credit');

                $purchaseOrder = PurchaseOrder::create([
                    'po_number' => 'PO-' . time(),
                    'branch_id' => $validated['branch_id'],
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                    'supplier_invoice_number' => $validated['supplier_invoice_number'] ?? null,
                    'order_date' => $parseDate($validated['order_date']),
                    'user_id' => auth()->id(),
                    'status' => $request->input('status', 'pending'),
                    'is_credit' => $isCredit,
                    'payment_terms' => $validated['payment_terms'] ?? ($isCredit ? 'credit' : 'cash'),
                    'expected_delivery_date' => $parseDate($validated['expected_delivery_date'] ?? null),
                    'payment_due_date' => $isCredit ? $parseDate($validated['payment_due_date'] ?? null) : null,
                    'payment_status' => $isCredit ? 'unpaid' : 'paid',
                    'subtotal' => $subtotal,
                    'tax_discount_amount' => $discount,
                    'tax_amount' => $tax,
                    'shipping_cost_charged' => $shipping,
                    'other_costs' => $other,
                    'total_amount' => $totalAmount,
                    'outstanding_amount' => $isCredit ? $totalAmount : 0,
                    'notes' => $validated['notes'] ?? null,
                ]);

                foreach ($validated['items'] as $item) {
                    $product = Product::find($item['product_id']);
                    PurchaseOrderDetail::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'branch_id' => $validated['branch_id'],
                        'product_id' => $item['product_id'],
                        'product_name' => $product?->name,
                        'ordered_quantity' => $item['quantity'],
                        'purchase_price' => $item['cost'],
                        'total_price' => $item['cost'] * $item['quantity'],
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
        return $purchaseOrder->load(['supplier', 'branch', 'user', 'purchaseOrderDetails.product', 'payments']);
    }

    // /**
    //  * Mark a purchase order as completed and update stock.
    //  */
    // public function receiveOrder(PurchaseOrder $purchaseOrder)
    // {
    //     if ($purchaseOrder->status !== 'pending') {
    //         return response()->json(['message' => 'This order cannot be received.'], 422);
    //     }

    //     try {
    //         DB::transaction(function () use ($purchaseOrder) {
    //             foreach ($purchaseOrder->purchaseOrderDetails as $detail) {
    //                 $product = Product::findOrFail($detail->product_id);

    //                 $stockBefore = $product->quantity;
    //                 $quantityChange = $detail->ordered_quantity; // Positif karena pembelian

    //                 // Tambah stok produk
    //                 $product->increment('quantity', $quantityChange);

    //                 // Catat mutasi stok
    //                 StockMutation::create([
    //                     'branch_id' => $purchaseOrder->branch_id,
    //                     'product_id' => $product->id,
    //                     'product_name' => $product->name,
    //                     'quantity_change' => $quantityChange,
    //                     'stock_before' => $stockBefore,
    //                     'stock_after' => $product->fresh()->quantity,
    //                     'type' => 'purchase',
    //                     'description' => 'Stock from PO ' . $purchaseOrder->po_number,
    //                     'reference_type' => PurchaseOrder::class,
    //                     'reference_id' => $purchaseOrder->id,
    //                     'user_id' => auth()->id(),
    //                     'user_name' => auth()->user()->name,
    //                 ]);
    //             }

    //             // Update status PO
    //             $purchaseOrder->update(['status' => 'completed']);
    //         });

    //         return response()->json(['message' => 'Order received and stock updated successfully.']);
    //     } catch (\Exception $e) {
    //         Log::error("Error receiving PO {$purchaseOrder->id}: " . $e->getMessage());
    //         return response()->json(['message' => 'Failed to receive order.'], 500);
    //     }
    // }

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

    /**
     * [BARU] Menerima barang per item (parsial atau penuh).
     * Ini akan menambah stok dan mengubah status PO menjadi 'partially_received' or 'completed'.
     */
    public function receiveOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        // Validasi: Pastikan PO bisa diterima & data item yang masuk valid
        if (! in_array($purchaseOrder->status, ['ordered', 'pending', 'partially_received'])) {
            return response()->json(['message' => 'Hanya PO dengan status "ordered", "pending" atau "partially received" yang bisa diterima.'], 422);
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.purchase_order_detail_id' => 'required|exists:purchase_order_details,id',
            'items.*.quantity_received' => 'required|integer|min:1',
        ]);

        try {
            DB::transaction(function () use ($purchaseOrder, $validated) {
                foreach ($validated['items'] as $itemData) {
                    $detail = PurchaseOrderDetail::find($itemData['purchase_order_detail_id']);
                    $product = Product::find($detail->product_id);

                    // Validasi agar jumlah terima tidak melebihi pesanan
                    $newReceivedQty = $detail->received_quantity + $itemData['quantity_received'];
                    if ($newReceivedQty > $detail->ordered_quantity) {
                        throw new \Exception("Jumlah terima untuk {$product->name} melebihi jumlah pesanan.");
                    }

                    // 1. Update jumlah diterima di detail PO
                    $detail->update(['received_quantity' => $newReceivedQty]);

                    // 2. Tambah stok produk & catat mutasi
                    if ($product) {
                        $stockBefore = $product->quantity;
                        $product->increment('quantity', $itemData['quantity_received']);

                        StockMutation::create([
                            'branch_id' => $purchaseOrder->branch_id,
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'quantity_change' => $itemData['quantity_received'],
                            'stock_before' => $stockBefore,
                            'stock_after' => $product->fresh()->quantity,
                            'type' => 'purchase',
                            'description' => "Penerimaan barang dari PO #{$purchaseOrder->po_number}",
                            'reference_type' => PurchaseOrder::class,
                            'reference_id' => $purchaseOrder->id,
                            'user_id' => auth()->id(),
                            'user_name' => auth()->user()->name,
                        ]);
                    }
                }

                // 3. Cek dan update status PO utama setelah semua item diproses
                $totalOrdered = $purchaseOrder->purchaseOrderDetails()->sum('ordered_quantity');
                $totalReceived = $purchaseOrder->purchaseOrderDetails()->sum('received_quantity');

                if ($totalReceived >= $totalOrdered) {
                    // Selaraskan dengan enum/status frontend: gunakan 'fully_received'
                    $newStatus = 'fully_received';
                } else {
                    $newStatus = 'partially_received';
                }

                $purchaseOrder->update(['status' => $newStatus]);
            });

            return response()->json(['message' => 'Barang berhasil diterima dan stok telah diperbarui.']);
        } catch (\Exception $e) {
            Log::error("Error receiving PO {$purchaseOrder->id}: " . $e->getMessage());

            return response()->json(['message' => 'Gagal menerima barang: ' . $e->getMessage()], 500);
        }
    }

    /**
     * [BARU] Mengubah status PO, misal dari 'draft' ke 'pending' (ordered).
     */
    public function updateStatus(Request $request, PurchaseOrder $purchaseOrder)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:draft,ordered,partially_received,fully_received,cancelled',
        ]);

        $oldStatus = $purchaseOrder->status;
        $newStatus = $validated['status'];

        // If reverting from received (partially/fully) to draft/ordered, rollback stock and received_quantity
        $receivedStatuses = ['partially_received', 'fully_received', 'completed'];
        $resetStatuses = ['draft', 'ordered'];
        if (in_array($oldStatus, $receivedStatuses) && in_array($newStatus, $resetStatuses)) {
            DB::transaction(function () use ($purchaseOrder, $newStatus) {
                foreach ($purchaseOrder->purchaseOrderDetails as $detail) {
                    $product = Product::find($detail->product_id);
                    if ($product && $detail->received_quantity > 0) {
                        $stockBefore = $product->quantity;
                        $product->decrement('quantity', $detail->received_quantity);
                        $stockAfter = $product->fresh()->quantity;
                        StockMutation::create([
                            'branch_id' => $purchaseOrder->branch_id,
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'quantity_change' => -$detail->received_quantity,
                            'stock_before' => $stockBefore,
                            'stock_after' => $stockAfter,
                            'type' => 'rollback',
                            'description' => 'Rollback PO #' . $purchaseOrder->po_number . ' ke status ' . $newStatus,
                            'reference_type' => PurchaseOrder::class,
                            'reference_id' => $purchaseOrder->id,
                            'user_id' => auth()->id(),
                            'user_name' => auth()->user() ? auth()->user()->name : null,
                        ]);
                    }
                    $detail->update(['received_quantity' => 0]);
                }
            });
        }

        $purchaseOrder->update(['status' => $newStatus]);

        return response()->json($purchaseOrder->fresh());
    }
}
