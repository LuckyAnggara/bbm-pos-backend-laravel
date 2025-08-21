<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomerAnalyticsController extends Controller
{
    /**
     * Get customer analytics data
     */
    public function analytics(Request $request, Customer $customer)
    {
        try {
            $months = $request->input('months', 12);
            $startDate = Carbon::now()->subMonths($months);

            // Get sales data for this customer
            $sales = Sale::where('customer_id', $customer->id)
                ->where('created_at', '>=', $startDate)
                ->where('status', 'completed')
                ->get();

            // Basic analytics
            $totalPurchases = $sales->count();
            $totalSpent = $sales->sum('total_amount');
            $averageOrderValue = $totalPurchases > 0 ? $totalSpent / $totalPurchases : 0;
            $lastPurchaseDate = $sales->max('created_at');
            $firstPurchaseDate = $sales->min('created_at');

            // Calculate purchase frequency (purchases per month)
            $purchaseFrequency = 0;
            if ($firstPurchaseDate && $lastPurchaseDate) {
                $firstDate = Carbon::parse($firstPurchaseDate);
                $lastDate = Carbon::parse($lastPurchaseDate);
                $monthsDiff = $firstDate->diffInMonths($lastDate) + 1;
                $purchaseFrequency = $monthsDiff > 0 ? $totalPurchases / $monthsDiff : 0;
            }

            // Favorite payment method
            $favoritePaymentMethod = $sales->groupBy('payment_method')
                ->map->count()
                ->sortDesc()
                ->keys()
                ->first();

            // Monthly spending data
            $monthlySpending = $sales->groupBy(function ($sale) {
                return Carbon::parse($sale->created_at)->format('Y-m');
            })
                ->map(function ($monthSales) {
                    return [
                        'total_spent' => $monthSales->sum('total_amount'),
                        'total_orders' => $monthSales->count(),
                    ];
                })
                ->map(function ($data, $month) {
                    return [
                        'month' => $month,
                        'total_spent' => $data['total_spent'],
                        'total_orders' => $data['total_orders'],
                    ];
                })
                ->values();

            // Top products purchased by this customer
            $topProducts = SaleDetail::whereIn('sale_id', $sales->pluck('id'))
                ->select('product_name')
                ->selectRaw('SUM(quantity) as quantity_purchased')
                ->selectRaw('SUM(subtotal) as total_spent')
                ->groupBy('product_name')
                ->orderBy('quantity_purchased', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'total_purchases' => $totalPurchases,
                'total_spent' => $totalSpent,
                'average_order_value' => round($averageOrderValue, 2),
                'last_purchase_date' => $lastPurchaseDate,
                'first_purchase_date' => $firstPurchaseDate,
                'purchase_frequency' => round($purchaseFrequency, 2),
                'favorite_payment_method' => $favoritePaymentMethod,
                'monthly_spending' => $monthlySpending,
                'top_products' => $topProducts,
            ]);
        } catch (\Exception $e) {
            Log::error("Error getting customer analytics for {$customer->id}: ".$e->getMessage());

            return response()->json(['message' => 'Failed to get customer analytics.'], 500);
        }
    }

    /**
     * Get customer sales history with pagination
     */
    public function sales(Request $request, Customer $customer)
    {
        try {
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 20);
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            $query = Sale::where('customer_id', $customer->id)
                ->with(['saleDetails.product', 'user'])
                ->orderBy('created_at', 'desc');

            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }

            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            $sales = $query->paginate($limit, ['*'], 'page', $page);

            return response()->json($sales);
        } catch (\Exception $e) {
            Log::error("Error getting customer sales for {$customer->id}: ".$e->getMessage());

            return response()->json(['message' => 'Failed to get customer sales.'], 500);
        }
    }

    /**
     * Get top customers (most frequent and highest spending)
     */
    public function topCustomers(Request $request)
    {
        try {
            $request->validate(['branch_id' => 'required|exists:branches,id']);

            $branchId = $request->input('branch_id');
            $limit = $request->input('limit', 10);
            $months = $request->input('months', 12);
            $startDate = Carbon::now()->subMonths($months);

            // Most frequent customers (by number of purchases)
            $mostFrequent = Customer::where('branch_id', $branchId)
                ->withCount(['sales as total_purchases' => function ($query) use ($startDate) {
                    $query->where('created_at', '>=', $startDate)
                        ->where('status', 'completed');
                }])
                ->with(['sales' => function ($query) use ($startDate) {
                    $query->where('created_at', '>=', $startDate)
                        ->where('status', 'completed')
                        ->select('customer_id', 'total_amount', 'created_at');
                }])
                ->having('total_purchases', '>', 0)
                ->orderBy('total_purchases', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($customer) {
                    $totalSpent = $customer->sales->sum('total_amount');
                    $lastPurchase = $customer->sales->max('created_at');
                    $firstPurchase = $customer->sales->min('created_at');

                    // Calculate purchase frequency
                    $purchaseFrequency = 0;
                    if ($firstPurchase && $lastPurchase) {
                        $monthsDiff = Carbon::parse($firstPurchase)->diffInMonths(Carbon::parse($lastPurchase)) + 1;
                        $purchaseFrequency = $monthsDiff > 0 ? $customer->total_purchases / $monthsDiff : 0;
                    }

                    return [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'email' => $customer->email,
                        'phone' => $customer->phone,
                        'total_purchases' => $customer->total_purchases,
                        'total_spent' => $totalSpent,
                        'last_purchase_date' => $lastPurchase,
                        'purchase_frequency' => round($purchaseFrequency, 2),
                    ];
                });

            // Highest spending customers
            $highestSpending = Customer::where('branch_id', $branchId)
                ->withSum(['sales as total_spent' => function ($query) use ($startDate) {
                    $query->where('created_at', '>=', $startDate)
                        ->where('status', 'completed');
                }], 'total_amount')
                ->withCount(['sales as total_purchases' => function ($query) use ($startDate) {
                    $query->where('created_at', '>=', $startDate)
                        ->where('status', 'completed');
                }])
                ->with(['sales' => function ($query) use ($startDate) {
                    $query->where('created_at', '>=', $startDate)
                        ->where('status', 'completed')
                        ->select('customer_id', 'created_at');
                }])
                ->having('total_spent', '>', 0)
                ->orderBy('total_spent', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($customer) {
                    $lastPurchase = $customer->sales->max('created_at');
                    $firstPurchase = $customer->sales->min('created_at');

                    // Calculate purchase frequency
                    $purchaseFrequency = 0;
                    if ($firstPurchase && $lastPurchase && $customer->total_purchases > 0) {
                        $monthsDiff = Carbon::parse($firstPurchase)->diffInMonths(Carbon::parse($lastPurchase)) + 1;
                        $purchaseFrequency = $monthsDiff > 0 ? $customer->total_purchases / $monthsDiff : 0;
                    }

                    return [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'email' => $customer->email,
                        'phone' => $customer->phone,
                        'total_purchases' => $customer->total_purchases,
                        'total_spent' => $customer->total_spent ?? 0,
                        'last_purchase_date' => $lastPurchase,
                        'purchase_frequency' => round($purchaseFrequency, 2),
                    ];
                });

            return response()->json([
                'most_frequent' => $mostFrequent,
                'highest_spending' => $highestSpending,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting top customers: '.$e->getMessage());

            return response()->json(['message' => 'Failed to get top customers.'], 500);
        }
    }
}
