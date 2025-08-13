<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\FinancialReport;
use App\Models\Sale;
use App\Models\Expense;

class FinancialReportController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|integer',
            'report_type' => 'required|string|in:sales_summary,income_statement,balance_sheet',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $report = FinancialReport::where($validated)->first();
        if (!$report) {
            return response()->json(['message' => 'Report not found'], 404);
        }
        return response()->json($report);
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|integer',
            'report_type' => 'required|string|in:sales_summary,income_statement,balance_sheet',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $branchId = (int) $validated['branch_id'];
        $type = $validated['report_type'];
        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = Carbon::parse($validated['end_date'])->endOfDay();

        // Compute data based on type
        if ($type === 'sales_summary') {
            $data = $this->computeSalesSummary($branchId, $start, $end);
        } elseif ($type === 'income_statement') {
            $data = $this->computeIncomeStatement($branchId, $start, $end);
        } else {
            return response()->json(['message' => 'Not implemented'], 400);
        }

        // Upsert by unique key
        $report = FinancialReport::updateOrCreate(
            [
                'branch_id' => $branchId,
                'report_type' => $type,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            [
                'data' => $data,
            ]
        );

        return response()->json($report, 201);
    }

    private function computeSalesSummary(int $branchId, $start, $end): array
    {
        $sales = Sale::where('branch_id', $branchId)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $completed = $sales->where('status', '!=', 'returned');
        $returned = $sales->where('status', 'returned');

        $totalValueReturned = (float) $returned->sum('total_amount');
        $netRevenue = (float) $completed->sum('total_amount');
        $grossRevenueBeforeReturns = $netRevenue + $totalValueReturned;

        $salesByPaymentMethod = [
            'cash' => 0.0,
            'card' => 0.0,
            'transfer' => 0.0,
        ];
        foreach ($completed as $s) {
            $pm = $s->payment_method;
            if (isset($salesByPaymentMethod[$pm])) {
                $salesByPaymentMethod[$pm] += (float) $s->total_amount;
            }
        }

        $totalNetTransactions = $completed->count();
        $averageTransactionValue = $totalNetTransactions > 0 ? $netRevenue / $totalNetTransactions : 0.0;

        return [
            'grossRevenueBeforeReturns' => $grossRevenueBeforeReturns,
            'totalValueReturned' => $totalValueReturned,
            'netRevenue' => $netRevenue,
            'totalNetTransactions' => $totalNetTransactions,
            'averageTransactionValue' => $averageTransactionValue,
            'salesByPaymentMethod' => $salesByPaymentMethod,
        ];
    }

    private function computeIncomeStatement(int $branchId, $start, $end): array
    {
        $sales = Sale::where('branch_id', $branchId)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $completed = $sales->where('status', '!=', 'returned');
        $returned = $sales->where('status', 'returned');

        $netRevenue = (float) $completed->sum('total_amount');
        $netCOGS = (float) $completed->sum('total_cogs');
        $totalValueReturned = (float) $returned->sum('total_amount');
        $cogsOfReturnedItems = (float) $returned->sum('total_cogs');

        $grossRevenueBeforeReturns = $netRevenue + $totalValueReturned;
        $grossCOGSBeforeReturns = $netCOGS + $cogsOfReturnedItems;

        $expenses = Expense::where('branch_id', $branchId)
            ->whereBetween('created_at', [$start, $end])
            ->get();
        $totalExpenses = (float) $expenses->sum('amount');

        $map = [];
        foreach ($expenses as $exp) {
            $cat = $exp->category ?? 'Lain-lain';
            $map[$cat] = ($map[$cat] ?? 0.0) + (float) $exp->amount;
        }
        $breakdown = [];
        foreach ($map as $cat => $amount) {
            $breakdown[] = ['category' => $cat, 'amount' => $amount];
        }

        $grossProfit = $netRevenue - $netCOGS;
        $netProfit = $grossProfit - $totalExpenses;

        return [
            'grossRevenueBeforeReturns' => $grossRevenueBeforeReturns,
            'totalValueReturned' => $totalValueReturned,
            'netRevenue' => $netRevenue,
            'grossCOGSBeforeReturns' => $grossCOGSBeforeReturns,
            'cogsOfReturnedItems' => $cogsOfReturnedItems,
            'netCOGS' => $netCOGS,
            'grossProfit' => $grossProfit,
            'totalExpenses' => $totalExpenses,
            'netProfit' => $netProfit,
            'expensesBreakdown' => $breakdown,
        ];
    }
}
