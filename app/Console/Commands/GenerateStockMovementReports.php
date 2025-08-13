<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Product;
use App\Services\StockMovementReportService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GenerateStockMovementReports extends Command
{
    protected $signature = 'reports:generate-stock-movement {--start=} {--end=}';

    protected $description = 'Generate and cache stock movement reports per product for all branches';

    public function handle(StockMovementReportService $service): int
    {
        $start = $this->option('start') ? CarbonImmutable::parse($this->option('start'))->startOfDay() : CarbonImmutable::parse('2025-01-01')->startOfDay();
        $end = $this->option('end') ? CarbonImmutable::parse($this->option('end'))->endOfDay() : CarbonImmutable::now()->endOfDay();

        $branches = Branch::all(['id']);
        foreach ($branches as $branch) {
            $products = Product::where('branch_id', $branch->id)->get(['id']);
            foreach ($products as $product) {
                $service->build($branch->id, $product->id, $start, $end, true);
            }
        }

        $this->info("Stock movement reports generated from {$start->toDateString()} to {$end->toDateString()}.");
        return self::SUCCESS;
    }
}
