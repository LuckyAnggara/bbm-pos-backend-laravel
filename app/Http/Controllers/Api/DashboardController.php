<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Return dashboard summary: sales/expense/profit aggregates, daily trends, top products, inventory snapshot.
     * Query params: branch_id (required), start_date, end_date (date, optional; defaults current month)
     */
    public function summary(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $branchId = (int) $validated['branch_id'];
        $start = $validated['start_date'] ? Carbon::parse($validated['start_date'])->startOfDay() : Carbon::now()->startOfMonth();
        $end = $validated['end_date'] ? Carbon::parse($validated['end_date'])->endOfDay() : Carbon::now()->endOfMonth();

        // Sales within range
        $sales = Sale::where('branch_id', $branchId)
            ->whereBetween('created_at', [$start, $end])
            ->get(['id', 'status', 'total_amount', 'total_cogs', 'subtotal', 'created_at']);

        $completed = $sales->where('status', 'completed');
        $returned = $sales->where('status', 'returned');

        $grossBeforeReturns = $completed->sum('total_amount');
        $returnedTotal = $returned->sum('total_amount');
        $netRevenue = $grossBeforeReturns - $returnedTotal; // simplistic net

        // Profit (sum of (total_amount - total_cogs) for completed minus returned impact)
        $grossProfit = $completed->sum(fn ($s) => ($s->total_amount - $s->total_cogs)) - $returned->sum(fn ($s) => ($s->total_amount - $s->total_cogs));

        // Expenses in range
        $expenses = Expense::where('branch_id', $branchId)
            ->whereBetween('created_at', [$start, $end])
            ->get(['amount', 'created_at']);
        $totalExpenses = $expenses->sum('amount');

        // Daily sales & profit trend
        $periodDays = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $periodDays[$key] = [
                'date' => $key,
                'sales' => 0,
                'profit' => 0,
            ];
            $cursor->addDay();
        }

        foreach ($sales as $s) {
            $d = Carbon::parse($s->created_at)->toDateString();
            if (! isset($periodDays[$d])) {
                continue;
            }
            $mult = $s->status === 'returned' ? -1 : ($s->status === 'completed' ? 1 : 0);
            if ($mult !== 0) {
                $periodDays[$d]['sales'] += $mult * $s->total_amount;
                $periodDays[$d]['profit'] += $mult * ($s->total_amount - $s->total_cogs);
            }
        }

        $dailySales = array_values(array_map(fn ($row) => [
            'date' => $row['date'],
            'total' => $row['sales'],
        ], $periodDays));

        $dailyProfit = array_values(array_map(fn ($row) => [
            'date' => $row['date'],
            'profit' => $row['profit'],
        ], $periodDays));

        // Top selling products (by quantity) in period
        $topProducts = SaleDetail::select('product_id', 'product_name', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(subtotal) as total_sales'))
            ->where('branch_id', $branchId)
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('qty')
            ->limit(10)
            ->get();

        // Inventory snapshot
        $totalUniqueProducts = Product::where('branch_id', $branchId)->count();
        $lowStockThreshold = 5;
        $lowStockItemsCount = Product::where('branch_id', $branchId)->where('quantity', '<', $lowStockThreshold)->count();

        return response()->json([
            'branch_id' => $branchId,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'gross_revenue_before_returns' => $grossBeforeReturns,
            'net_revenue' => $netRevenue,
            'gross_profit' => $grossProfit,
            'total_expenses' => $totalExpenses,
            'net_transaction_count' => $completed->count(),
            'daily_sales' => $dailySales,
            'daily_profit' => $dailyProfit,
            'top_products' => $topProducts,
            'inventory' => [
                'total_unique_products' => $totalUniqueProducts,
                'low_stock_items_count' => $lowStockItemsCount,
                'low_stock_threshold' => $lowStockThreshold,
            ],
        ]);
    }
}
