<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource with filtering and pagination.
     */
    public function index(Request $request)
    {
        $limit = $request->input('limit', 15);
        $query = Expense::with(['branch:id,name', 'user:id,name']);

        // Filter berdasarkan branch_id (Wajib ada)
        $request->validate(['branch_id' => 'required|exists:branches,id']);
        $query->where('branch_id', $request->branch_id);

        // Filter berdasarkan kategori (mendukung single atau array)
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->has('categories')) {
            $categories = $request->input('categories');
            if (is_array($categories) && count($categories) > 0) {
                $query->whereIn('category', $categories);
            }
        }

        // Filter berdasarkan rentang tanggal (inklusif untuk keseluruhan hari)
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereDate('created_at', '>=', $request->start_date)
                ->whereDate('created_at', '<=', $request->end_date);
        }

        // Pencarian sederhana pada deskripsi atau kategori
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $expenses = $query->latest()->paginate($limit);

        return response()->json($expenses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'category' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|gt:0',
        ]);

        try {
            $expense = DB::transaction(function () use ($validated) {
                return Expense::create(array_merge($validated, [
                    'user_id' => auth()->id(),
                ]));
            });

            return response()->json($expense, 201);
        } catch (\Exception $e) {
            Log::error('Error creating expense: '.$e->getMessage());

            return response()->json(['message' => 'Failed to create expense.'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Expense $expense)
    {
        return $expense->load(['branch', 'user']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expense $expense)
    {
        $validated = $request->validate([
            'branch_id' => 'sometimes|required|exists:branches,id',
            'category' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'amount' => 'sometimes|required|numeric|gt:0',
        ]);

        try {
            DB::transaction(function () use ($expense, $validated) {
                $expense->update($validated);
            });

            return response()->json($expense);
        } catch (\Exception $e) {
            Log::error("Error updating expense {$expense->id}: ".$e->getMessage());

            return response()->json(['message' => 'Failed to update expense.'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expense $expense)
    {
        try {
            DB::transaction(function () use ($expense) {
                $expense->delete();
            });

            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error("Error deleting expense {$expense->id}: ".$e->getMessage());

            return response()->json(['message' => 'Failed to delete expense.'], 500);
        }
    }
}
