<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BankAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = BankAccount::query();

        // Logika baru untuk mengambil data
        if ($request->filled('branch_id')) {
            $branchId = $request->branch_id;

            // Ambil akun yang branch_id-nya cocok DENGAN cabang yang diminta
            // ATAU yang branch_id-nya NULL (global)
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            });
        }

        // Jika tidak ada branch_id yang diminta, ambil semua (termasuk global)
        // atau Anda bisa tentukan aturan lain, misal hanya ambil yang global.
        // Untuk saat ini, kita biarkan ia mengambil semua jika tidak ada filter.

        return $query->latest()->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($request->branch_id === 'NONE') {
            $request->merge(['branch_id' => null]);
        }

        $validated = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'account_holder_name' => 'required|string|max:255',
            'is_active' => 'required|boolean',
            'is_default' => 'required|boolean',
        ]);

        try {
            $bankAccount = DB::transaction(function () use ($validated) {
                // Jika ini diatur sebagai default, nonaktifkan default lainnya di cabang yang sama
                if ($validated['is_default']) {
                    BankAccount::where('branch_id', $validated['branch_id'])
                        ->where('is_default', true)
                        ->update(['is_default' => false]);
                }

                return BankAccount::create($validated);
            });

            return response()->json($bankAccount, 201);
        } catch (\Exception $e) {
            Log::error('Error creating bank account: '.$e->getMessage());

            return response()->json(['message' => 'Failed to create bank account.'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(BankAccount $bankAccount)
    {
        return $bankAccount->load('branch');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BankAccount $bankAccount)
    {
        if ($request->branch_id === 'NONE') {
            $request->merge(['branch_id' => null]);
        }

        $validated = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'bank_name' => 'sometimes|required|string|max:255',
            'account_number' => 'sometimes|required|string|max:255',
            'account_holder_name' => 'sometimes|required|string|max:255',
            'is_active' => 'sometimes|required|boolean',
            'is_default' => 'sometimes|required|boolean',
        ]);

        try {
            DB::transaction(function () use ($bankAccount, $validated) {
                // Jika ini diatur sebagai default, nonaktifkan default lainnya di cabang yang sama
                if (isset($validated['is_default']) && $validated['is_default']) {
                    BankAccount::where('branch_id', $bankAccount->branch_id)
                        ->where('is_default', true)
                        ->where('id', '!=', $bankAccount->id) // Jangan update diri sendiri
                        ->update(['is_default' => false]);
                }
                $bankAccount->update($validated);
            });

            return response()->json($bankAccount);
        } catch (\Exception $e) {
            Log::error("Error updating bank account {$bankAccount->id}: ".$e->getMessage());

            return response()->json(['message' => 'Failed to update bank account.'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BankAccount $bankAccount)
    {
        try {
            DB::transaction(function () use ($bankAccount) {
                $bankAccount->delete();
            });

            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error("Error deleting bank account {$bankAccount->id}: ".$e->getMessage());

            return response()->json(['message' => 'Failed to delete bank account.'], 500);
        }
    }
}
