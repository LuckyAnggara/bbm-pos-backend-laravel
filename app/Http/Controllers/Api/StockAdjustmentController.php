<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockAdjustmentController extends Controller
{
    /**
     * Store a new stock adjustment.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'branch_id' => 'required|exists:branches,id',
            'quantity_change' => 'required|integer|not_in:0',
            'description' => 'required|string|max:255',
        ]);

        try {
            $adjustment = DB::transaction(function () use ($validated) {
                $product = Product::findOrFail($validated['product_id']);
                $user = auth()->user();

                $stockBefore = $product->quantity;
                $stockAfter = $stockBefore + $validated['quantity_change'];

                // Update stok produk
                $product->update(['quantity' => $stockAfter]);

                // Buat catatan mutasi
                return StockMutation::create([
                    'branch_id' => $validated['branch_id'],
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity_change' => $validated['quantity_change'],
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'type' => 'adjustment', // Tipe khusus untuk penyesuaian
                    'description' => $validated['description'],
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                ]);
            });

            return response()->json($adjustment, 201);
        } catch (\Exception $e) {
            Log::error('Stock Adjustment Error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to adjust stock.'], 500);
        }
    }
}
