<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Services\StockMutationReportService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class GenerateStockMutationReports extends Command
{
    protected $signature = 'reports:generate-stock-mutation {--date=}';

    protected $description = 'Generate cached Stock Mutation reports for all branches up to given end date (default: today) starting from 2025-01-01';

    public function handle(StockMutationReportService $service)
    {
        $endDateInput = $this->option('date');
        $endDate = $endDateInput ? CarbonImmutable::parse($endDateInput)->endOfDay() : CarbonImmutable::now()->endOfDay();
        $startDate = CarbonImmutable::parse('2025-01-01')->startOfDay();

        $this->info("Generating stock mutation reports up to {$endDate->toDateString()}...");

        $count = 0;
        foreach (Branch::all() as $branch) {
            $service->compute((int) $branch->id, $startDate, $endDate, true);
            $count++;
        }

        $this->info("Generated reports for {$count} branches.");

        return self::SUCCESS;
    }
}
