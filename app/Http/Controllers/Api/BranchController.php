<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BranchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Branch::latest()->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'invoice_name' => 'required|string|max:255',
            'printer_port' => 'nullable|string|max:255',
            'default_report_period' => 'sometimes|string|max:255',
            'transaction_delete_password' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'currency' => 'sometimes|string|max:10',
            'tax_rate' => 'sometimes|numeric|min:0',
            'phone_number' => 'nullable|string|max:50',
        ]);

        try {
            $branch = DB::transaction(function () use ($validated) {
                return Branch::create($validated);
            });

            return response()->json($branch, 201);
        } catch (\Exception $e) {
            Log::error('Error creating branch: '.$e->getMessage());

            return response()->json(['message' => 'Failed to create branch. Please try again.'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Branch $branch)
    {
        // Load relasi users untuk melihat siapa saja yang ada di cabang ini
        return $branch->load('users');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'invoice_name' => 'sometimes|required|string|max:255',
            'printer_port' => 'nullable|string|max:255',
            'default_report_period' => 'sometimes|string|max:255',
            'transaction_delete_password' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'currency' => 'sometimes|string|max:10',
            'tax_rate' => 'sometimes|numeric|min:0',
            'phone_number' => 'nullable|string|max:50',
        ]);

        try {
            DB::transaction(function () use ($branch, $validated) {
                $branch->update($validated);
            });

            return response()->json($branch);
        } catch (\Exception $e) {
            Log::error("Error updating branch {$branch->id}: ".$e->getMessage());

            return response()->json(['message' => 'Failed to update branch. Please try again.'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Branch $branch)
    {
        // Peringatan: Menghapus cabang bisa berdampak pada user yang terhubung.
        // Relasi di migrasi (onDelete('set null')) akan menangani ini
        // dengan mengatur branch_id di tabel users menjadi NULL.
        $branch->delete();

        return response()->json(null, 204);
    }
}
