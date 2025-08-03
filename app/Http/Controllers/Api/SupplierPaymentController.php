<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\SupplierPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SupplierPaymentController extends Controller
{
    /**
     * Store a new payment for a purchase order.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'amount_paid' => 'required|numeric|gt:0', // Harus lebih besar dari 0
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
        ]);

        try {
            $payment = DB::transaction(function () use ($validated) {
                $purchaseOrder = PurchaseOrder::findOrFail($validated['purchase_order_id']);

                // Validasi agar pembayaran tidak melebihi sisa hutang
                if ($validated['amount_paid'] > $purchaseOrder->outstanding_amount) {
                    throw new \Exception('Payment amount exceeds the outstanding balance.');
                }

                // Buat record pembayaran
                $supplierPayment = SupplierPayment::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'branch_id' => $purchaseOrder->branch_id,
                    'supplier_id' => $purchaseOrder->supplier_id,
                    'payment_date' => Carbon::parse($validated['payment_date']),
                    'amount_paid' => $validated['amount_paid'],
                    'payment_method' => $validated['payment_method'],
                    'recorded_by_user_id' => auth()->id(),
                ]);

                // Update sisa hutang dan status pembayaran di Purchase Order
                $newOutstandingAmount = $purchaseOrder->outstanding_amount - $validated['amount_paid'];
                $newPaymentStatus = $newOutstandingAmount <= 0 ? 'paid' : 'partially_paid';

                $purchaseOrder->update([
                    'outstanding_amount' => $newOutstandingAmount,
                    'payment_status' => $newPaymentStatus,
                ]);

                return $supplierPayment;
            });

            return response()->json($payment, 201);
        } catch (\Exception $e) {
            Log::error("Supplier Payment Error: " . $e->getMessage());
            return response()->json(['message' => 'Failed to record payment: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display a listing of the payments for a specific purchase order.
     */
    public function index(Request $request)
    {
        $request->validate(['purchase_order_id' => 'required|exists:purchase_orders,id']);

        $payments = SupplierPayment::where('purchase_order_id', $request->purchase_order_id)
            ->latest()
            ->get();

        return response()->json($payments);
    }
}
