<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerPayment;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerPaymentController extends Controller
{
    /**
     * List payments by sale_id
     */
    public function index(Request $request)
    {
        $saleId = $request->query('sale_id');
        $query = CustomerPayment::query()->with(['customer:id,name', 'branch:id,name']);
        if ($saleId) {
            $query->where('sale_id', $saleId);
        }

        return $query->latest('payment_date')->paginate($request->input('limit', 50));
    }

    /**
     * Store a new customer payment and update sale outstanding/payment_status
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'payment_date' => 'required|date',
            'amount_paid' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $user = $request->user();

        return DB::transaction(function () use ($validated, $user) {
            $sale = Sale::lockForUpdate()->findOrFail($validated['sale_id']);

            if ($validated['amount_paid'] > ($sale->outstanding_amount ?? 0)) {
                return response()->json([
                    'message' => 'Jumlah bayar melebihi sisa piutang.',
                ], 422);
            }

            $payment = CustomerPayment::create([
                'sale_id' => $sale->id,
                'branch_id' => $sale->branch_id,
                'customer_id' => $sale->customer_id,
                'payment_date' => $validated['payment_date'],
                'amount_paid' => $validated['amount_paid'],
                'payment_method' => $validated['payment_method'],
                'notes' => $validated['notes'] ?? null,
                'recorded_by_user_id' => $user->id,
            ]);

            $newOutstanding = max(0, (float) ($sale->outstanding_amount ?? 0) - (float) $validated['amount_paid']);
            $sale->outstanding_amount = $newOutstanding;
            $sale->payment_status = $newOutstanding <= 0 ? 'paid' : 'partially_paid';
            $sale->save();

            return response()->json($payment->fresh(), 201);
        });
    }

    /**
     * Update an existing customer payment and adjust sale outstanding
     */
    public function update(Request $request, CustomerPayment $customer_payment)
    {
        $validated = $request->validate([
            'payment_date' => 'sometimes|date',
            'amount_paid' => 'sometimes|numeric|min:0.01',
            'payment_method' => 'sometimes|string',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $customer_payment) {
            $sale = Sale::lockForUpdate()->findOrFail($customer_payment->sale_id);

            $oldAmount = (float) $customer_payment->amount_paid;
            $newAmount = isset($validated['amount_paid']) ? (float) $validated['amount_paid'] : $oldAmount;
            $delta = $newAmount - $oldAmount; // positive increases payment

            // allowed max = outstanding + old amount
            $allowedMax = (float) ($sale->outstanding_amount ?? 0) + $oldAmount;
            if ($newAmount > $allowedMax) {
                return response()->json([
                    'message' => 'Jumlah bayar melebihi sisa piutang.',
                ], 422);
            }

            $customer_payment->update($validated);

            $newOutstanding = max(0, (float) ($sale->outstanding_amount ?? 0) - $delta);
            $sale->outstanding_amount = $newOutstanding;
            $sale->payment_status = $newOutstanding <= 0 ? 'paid' : 'partially_paid';
            $sale->save();

            return response()->json($customer_payment->fresh());
        });
    }

    /**
     * Delete a customer payment and revert its impact on sale outstanding/status
     */
    public function destroy(Request $request, CustomerPayment $customer_payment)
    {
        return DB::transaction(function () use ($customer_payment) {
            $sale = Sale::lockForUpdate()->findOrFail($customer_payment->sale_id);

            $amount = (float) $customer_payment->amount_paid;

            // Delete payment first
            $customer_payment->delete();

            // Revert outstanding and status
            $sale->outstanding_amount = (float) ($sale->outstanding_amount ?? 0) + $amount;
            $sale->payment_status = $sale->outstanding_amount > 0
                ? ($sale->amount_paid > 0 ? 'partially_paid' : 'unpaid')
                : 'paid';
            $sale->save();

            return response()->json(['message' => 'Deleted']);
        });
    }
}
