<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockMovementReport;
use App\Services\StockMovementReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class StockMovementReportController extends Controller
{
    // Fetch cached report for a product (fixed start date from request) with pagination handled on frontend
    public function index(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|integer',
            'product_id' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $report = StockMovementReport::where('branch_id', $validated['branch_id'])
            ->where('product_id', $validated['product_id'])
            ->where('start_date', $validated['start_date'])
            ->where('end_date', $validated['end_date'])
            ->first();

        if (! $report) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()->json($report);
    }

    // Generate report - locked for users; will be executed by cron/command
    public function generate(Request $request, StockMovementReportService $service)
    {
        return response()->json(['message' => 'Generate report hanya dijalankan oleh sistem.'], 403);
    }

    // Live compute without persisting
    public function live(Request $request, StockMovementReportService $service)
    {
        $validated = $request->validate([
            'branch_id' => 'required|integer',
            'product_id' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $branchId = (int) $validated['branch_id'];
        $productId = (int) $validated['product_id'];
        $startDate = CarbonImmutable::parse($validated['start_date'])->startOfDay();
        $endDate = CarbonImmutable::parse($validated['end_date'])->endOfDay();

        $result = $service->build($branchId, $productId, $startDate, $endDate, false);

        return response()->json([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'data' => $result['movements'],
            'initial_stock' => $result['initial_stock'],
            'current_stock' => $result['current_stock'],
        ]);
    }
}
