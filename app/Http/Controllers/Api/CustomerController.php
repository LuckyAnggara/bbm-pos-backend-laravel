<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource with pagination and search.
     */
    public function index(Request $request)
    {
        try {
            $limit = $request->input('limit', 5);
            $search = $request->input('search');

            // Filter berdasarkan branch_id (Wajib ada)
            $request->validate(['branch_id' => 'required|exists:branches,id']);
            $query = Customer::with('branch');

            $query->where('branch_id', $request->branch_id);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            $customers = $query->latest()->paginate($limit);

            return response()->json($customers);
        } catch (\Exception $e) {
            Log::error('Error fetching customers: ' . $e->getMessage());

            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:customers,email',
            'phone' => 'nullable|string|max:20|unique:customers,phone',
            'address' => 'nullable|string',
            'branch_id' => 'required|exists:branches,id',
            'notes' => 'nullable|string',
            // Customer Classification
            'customer_type' => 'nullable|in:individual,business',
            'customer_tier' => 'nullable|in:regular,silver,gold,platinum',
            'company_name' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'business_type' => 'nullable|string|max:100',
            // Credit Management (only for business customers)
            'credit_limit' => 'nullable|numeric|min:0',
            'payment_terms_days' => 'nullable|integer|min:0|max:365',
            'credit_status' => 'nullable|in:active,suspended,blocked',
            // Preferences
            'preferences' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        // Set defaults for new customers
        $validated['customer_type'] = $validated['customer_type'] ?? 'individual';
        $validated['customer_tier'] = $validated['customer_tier'] ?? 'regular';
        $validated['credit_limit'] = $validated['credit_limit'] ?? 0;
        $validated['payment_terms_days'] = $validated['payment_terms_days'] ?? 0;
        $validated['credit_status'] = $validated['credit_status'] ?? 'active';
        $validated['is_active'] = $validated['is_active'] ?? true;

        // Business validation
        if ($validated['customer_type'] === 'business') {
            $request->validate([
                'company_name' => 'required|string|max:255',
            ]);
        }

        try {
            $customer = DB::transaction(function () use ($validated) {
                return Customer::create($validated);
            });

            return response()->json($customer->load('branch'), 201);
        } catch (\Exception $e) {
            Log::error('Error creating customer: ' . $e->getMessage());

            return response()->json(['message' => 'Failed to create customer.'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        // Muat relasi untuk melihat riwayat penjualan pelanggan
        return response()->json($customer->load(['branch', 'sales']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255', Rule::unique('customers')->ignore($customer->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20', Rule::unique('customers')->ignore($customer->id)],
            'address' => 'sometimes|nullable|string',
            'branch_id' => 'sometimes|required|exists:branches,id',
            'notes' => 'sometimes|nullable|string',
            // Customer Classification
            'customer_type' => 'sometimes|in:individual,business',
            'customer_tier' => 'sometimes|in:regular,silver,gold,platinum',
            'company_name' => 'sometimes|nullable|string|max:255',
            'tax_id' => 'sometimes|nullable|string|max:50',
            'business_type' => 'sometimes|nullable|string|max:100',
            // Credit Management
            'credit_limit' => 'sometimes|nullable|numeric|min:0',
            'payment_terms_days' => 'sometimes|nullable|integer|min:0|max:365',
            'credit_status' => 'sometimes|in:active,suspended,blocked',
            // Preferences
            'preferences' => 'sometimes|nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        // Business validation when changing to business type
        if (isset($validated['customer_type']) && $validated['customer_type'] === 'business') {
            $request->validate([
                'company_name' => 'required|string|max:255',
            ]);
        }

        try {
            DB::transaction(function () use ($customer, $validated) {
                $customer->update($validated);
            });

            return response()->json($customer->load('branch'));
        } catch (\Exception $e) {
            Log::error('Error updating customer: ' . $e->getMessage());

            return response()->json(['message' => 'Failed to update customer.'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        try {
            DB::transaction(function () use ($customer) {
                $customer->delete();
            });

            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error("Error deleting customer {$customer->id}: " . $e->getMessage());

            return response()->json(['message' => 'Failed to delete customer.'], 500);
        }
    }
}
