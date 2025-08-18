<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesReportController extends Controller
{
    public function index(Request $request)
    {
        try {
            $startDate = $request->input('start_date', Carbon::now()->startOfMonth());
            $endDate = $request->input('end_date', Carbon::now()->endOfMonth());
            $branchId = $request->input('branch_id');

            // Parse dates properly
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();

            // Get sales data per sales employee
            $query = DB::table('sales')
                ->join('employees', 'sales.sales_id', '=', 'employees.id')
                ->join('branches', 'employees.branch_id', '=', 'branches.id')
                ->select([
                    'employees.id as employee_id',
                    'employees.name as sales_name',
                    'employees.employee_code',
                    'branches.name as branch_name',
                    DB::raw('COUNT(sales.id) as total_transactions'),
                    DB::raw('SUM(sales.total_amount) as total_sales'),
                    DB::raw('SUM(sales.total_cogs) as total_cogs'),
                    DB::raw('SUM(sales.total_amount - sales.total_cogs) as total_profit'),
                    DB::raw('AVG(sales.total_amount) as avg_transaction_value')
                ])
                ->where('employees.is_sales', true)
                ->where('sales.status', 'completed')
                ->whereBetween('sales.created_at', [$startDate, $endDate]);

            // Filter by branch if specified
            if ($branchId && $branchId !== 'all') {
                $query->where('employees.branch_id', $branchId);
            }

            $salesData = $query->groupBy([
                'employees.id',
                'employees.name',
                'employees.employee_code',
                'branches.name'
            ])
                ->orderByDesc('total_sales')
                ->get();

            // Get summary data
            $summary = [
                'total_sales_employees' => $salesData->count(),
                'total_sales_amount' => $salesData->sum('total_sales'),
                'total_profit' => $salesData->sum('total_profit'),
                'total_transactions' => $salesData->sum('total_transactions'),
                'avg_sales_per_employee' => $salesData->count() > 0 ? $salesData->sum('total_sales') / $salesData->count() : 0
            ];

            // Get top performing sales employee
            $topSales = $salesData->first();

            return response()->json([
                'sales_data' => $salesData,
                'summary' => $summary,
                'top_sales' => $topSales,
                'period' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d')
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching sales report: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getSalesEmployees(Request $request)
    {
        try {
            $branchId = $request->input('branch_id');

            $query = Employee::select('id', 'name', 'employee_code', 'branch_id')
                ->with('branch:id,name')
                ->where('is_sales', true)
                ->where('status', 'active');

            if ($branchId && $branchId !== 'all') {
                $query->where('branch_id', $branchId);
            }

            $salesEmployees = $query->orderBy('name')->get();

            return response()->json($salesEmployees);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching sales employees: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getSalesDetail(Request $request, $employeeId)
    {
        try {
            $startDate = $request->input('start_date', Carbon::now()->startOfMonth());
            $endDate = $request->input('end_date', Carbon::now()->endOfMonth());

            // Parse dates properly
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();

            $employee = Employee::with('branch')->findOrFail($employeeId);

            if (!$employee->is_sales) {
                return response()->json(['message' => 'Employee is not a sales person'], 400);
            }

            // Get detailed sales for this employee
            $sales = Sale::with(['customer', 'saleDetails.product'])
                ->where('sales_id', $employeeId)
                ->where('status', 'completed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'desc')
                ->get();

            // Calculate statistics
            $stats = [
                'total_transactions' => $sales->count(),
                'total_sales' => $sales->sum('total_amount'),
                'total_profit' => $sales->sum(function ($sale) {
                    return $sale->total_amount - $sale->total_cogs;
                }),
                'avg_transaction_value' => $sales->count() > 0 ? $sales->sum('total_amount') / $sales->count() : 0,
                'best_day_sales' => $sales->groupBy(function ($sale) {
                    return $sale->created_at->format('Y-m-d');
                })->map(function ($daySales) {
                    return $daySales->sum('total_amount');
                })->max() ?? 0
            ];

            return response()->json([
                'employee' => $employee,
                'sales' => $sales,
                'stats' => $stats,
                'period' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d')
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching sales detail: ' . $e->getMessage()
            ], 500);
        }
    }
}
