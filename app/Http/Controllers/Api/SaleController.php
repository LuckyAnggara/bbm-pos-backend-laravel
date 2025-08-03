<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SaleController extends Controller
{
    /**
     * Display a listing of sales with pagination and filtering.
     */
    public function index(Request $request)
    {
        try {
            $limit = $request->input('limit', 20);

            $query = Sale::with(['user:id,name', 'customer:id,name', 'branch:id,name']);

            // Filter by date range
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            }

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Filter by payment status
            if ($request->filled('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            // Search by transaction number or customer name
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('transaction_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%");
                });
            }

            $sales = $query->latest()->paginate($limit);

            return response()->json($sales);
        } catch (\Exception $e) {
            Log::error('Error fetching sales: ' . $e->getMessage());
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Display the specified sale along with its details.
     */
    public function show(Sale $sale)
    {
        // Load all related data for a complete view of the transaction
        return $sale->load([
            'user:id,name,email',
            'customer',
            'branch',
            // Load sale details, and for each detail, load the product info
            'saleDetails.product:id,name,sku'
        ]);
    }
}
