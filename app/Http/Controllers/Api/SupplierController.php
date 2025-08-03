<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource with pagination and search.
     */
    public function index(Request $request)
    {
        try {
            $limit = $request->input('limit', 15);
            $search = $request->input('search');

            $query = Supplier::with('branch:id,name');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%");
                });
            }

            $suppliers = $query->latest()->paginate($limit);

            return response()->json($suppliers);
        } catch (\Exception $e) {
            Log::error('Error fetching suppliers: ' . $e->getMessage());
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
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:suppliers,email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'branch_id' => 'required|exists:branches,id',
        ]);

        try {
            $supplier = DB::transaction(function () use ($validated) {
                return Supplier::create($validated);
            });
            return response()->json($supplier, 201);
        } catch (\Exception $e) {
            Log::error('Error creating supplier: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create supplier.'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier)
    {
        return response()->json($supplier->load(['branch', 'purchaseOrders']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'contact_person' => 'sometimes|nullable|string|max:255',
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255', Rule::unique('suppliers')->ignore($supplier->id)],
            'phone' => 'sometimes|nullable|string|max:20',
            'address' => 'sometimes|nullable|string',
            'notes' => 'sometimes|nullable|string',
            'branch_id' => 'sometimes|required|exists:branches,id',
        ]);

        try {
            DB::transaction(function () use ($supplier, $validated) {
                $supplier->update($validated);
            });
            return response()->json($supplier);
        } catch (\Exception $e) {
            Log::error("Error updating supplier {$supplier->id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to update supplier.'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier)
    {
        try {
            DB::transaction(function () use ($supplier) {
                $supplier->delete();
            });
            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error("Error deleting supplier {$supplier->id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to delete supplier.'], 500);
        }
    }
}
