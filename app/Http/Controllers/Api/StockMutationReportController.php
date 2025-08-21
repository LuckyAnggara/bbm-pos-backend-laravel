<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockMutationReport;
use App\Services\StockMutationReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class StockMutationReportController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|integer',
            'end_date' => 'required|date',
        ]);

        // Fixed start date from Jan 1, 2025
        $fixedStart = CarbonImmutable::parse('2025-01-01')->startOfDay();
        $end = CarbonImmutable::parse($validated['end_date'])->endOfDay();

        $report = StockMutationReport::where('branch_id', $validated['branch_id'])
            ->where('start_date', $fixedStart->toDateString())
            ->where('end_date', $validated['end_date'])
            ->first();

        if (! $report) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        return response()->json($report);
    }

    public function generate(Request $request, StockMutationReportService $service)
    {
        return response()->json(['message' => 'Generate report hanya dijalankan oleh sistem.'], 403);
    }

    // Live report endpoint: compute from fixed start to today without persisting.
    public function live(Request $request, StockMutationReportService $service)
    {
        $validated = $request->validate([
            'branch_id' => 'required|integer',
        ]);

        $branchId = (int) $validated['branch_id'];
        $startDate = CarbonImmutable::parse('2025-01-01')->startOfDay();
        $endDate = CarbonImmutable::now()->endOfDay();

        $result = $service->compute($branchId, $startDate, $endDate, false);

        return response()->json([
            'branch_id' => $branchId,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'data' => $result['items'],
        ]);
    }
}
