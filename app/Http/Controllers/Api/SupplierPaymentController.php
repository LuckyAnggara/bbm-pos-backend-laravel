<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\SupplierPayment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            'notes' => 'nullable|string|max:500',
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
                    'notes' => $validated['notes'] ?? null,
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
            Log::error('Supplier Payment Error: '.$e->getMessage());

            return response()->json(['message' => 'Failed to record payment: '.$e->getMessage()], 500);
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

    /**
     * Update an existing supplier payment and adjust the related PO's outstanding amount.
     */
    public function update(Request $request, SupplierPayment $supplierPayment)
    {
        $validated = $request->validate([
            'payment_date' => 'sometimes|date',
            'amount_paid' => 'sometimes|numeric|gt:0',
            'payment_method' => 'sometimes|string',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $updatedPayment = DB::transaction(function () use ($supplierPayment, $validated) {
                $purchaseOrder = $supplierPayment->purchaseOrder()->lockForUpdate()->first();

                $oldAmount = (float) $supplierPayment->amount_paid;
                $newAmount = array_key_exists('amount_paid', $validated)
                    ? (float) $validated['amount_paid']
                    : $oldAmount;

                // Prevent overpayment beyond current outstanding + the old amount of this payment
                $allowedMax = (float) $purchaseOrder->outstanding_amount + $oldAmount;
                if ($newAmount > $allowedMax) {
                    abort(422, 'Payment amount exceeds the outstanding balance.');
                }

                $delta = $newAmount - $oldAmount; // positive reduces outstanding
                $newOutstanding = (float) $purchaseOrder->outstanding_amount - $delta;
                if ($newOutstanding < 0) {
                    $newOutstanding = 0.0;
                }

                // Update payment fields
                $supplierPayment->update([
                    'payment_date' => array_key_exists('payment_date', $validated)
                        ? \Carbon\Carbon::parse($validated['payment_date'])
                        : $supplierPayment->payment_date,
                    'amount_paid' => $newAmount,
                    'payment_method' => $validated['payment_method'] ?? $supplierPayment->payment_method,
                    'notes' => $validated['notes'] ?? $supplierPayment->notes,
                ]);

                // Update PO outstanding and status
                $purchaseOrder->update([
                    'outstanding_amount' => $newOutstanding,
                    'payment_status' => $newOutstanding <= 0 ? 'paid' : 'partially_paid',
                ]);

                return $supplierPayment->fresh();
            });

            return response()->json($updatedPayment);
        } catch (\Exception $e) {
            Log::error('Supplier Payment Update Error: '.$e->getMessage());

            return response()->json(['message' => 'Failed to update payment: '.$e->getMessage()], 500);
        }
    }
}
