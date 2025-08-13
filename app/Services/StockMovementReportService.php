<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMutation;
use App\Models\StockMovementReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class StockMovementReportService
{
    /**
     * Build detailed movement list for a product between dates, optionally persist.
     * Returns: ['movements' => [...], 'saved' => bool, 'initial_stock' => int, 'current_stock' => int]
     */
    public function build(
        int $branchId,
        int $productId,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        bool $persist = true
    ): array {
        $product = Product::where('branch_id', $branchId)->findOrFail($productId);

        $initialStock = $this->getStockLevelAtDate($productId, $branchId, $startDate->subDay()->endOfDay());

        $mutations = StockMutation::where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $rows = [];
        $rows[] = [
            'id' => 'initial-stock-row',
            'branchId' => $branchId,
            'productId' => (string) $productId,
            'productName' => $product->name,
            'sku' => $product->sku,
            'mutationTime' => $startDate->toIso8601String(),
            'type' => 'INITIAL_STOCK',
            'quantityChange' => 0,
            'stockBeforeMutation' => $initialStock,
            'stockAfterMutation' => $initialStock,
            'createdAt' => $startDate->toIso8601String(),
            'notes' => 'Stok Awal Periode',
        ];

        foreach ($mutations as $m) {
            $rows[] = [
                'id' => (string) $m->id,
                'branchId' => $m->branch_id,
                'productId' => (string) $m->product_id,
                'productName' => $m->product_name,
                'sku' => $product->sku,
                'mutationTime' => $m->created_at?->toIso8601String(),
                'type' => strtoupper($m->type),
                'quantityChange' => (int) $m->quantity_change,
                'stockBeforeMutation' => (int) $m->stock_before,
                'stockAfterMutation' => (int) $m->stock_after,
                'createdAt' => $m->created_at?->toIso8601String(),
                'notes' => $m->description,
                'referenceType' => $m->reference_type,
                'referenceId' => $m->reference_id,
            ];
        }

        $saved = false;
        if ($persist) {
            StockMovementReport::updateOrCreate(
                [
                    'branch_id' => $branchId,
                    'product_id' => $productId,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
                [
                    'data' => $rows,
                    'generated_at' => now(),
                ]
            );
            $saved = true;
        }

        return [
            'movements' => $rows,
            'saved' => $saved,
            'initial_stock' => $initialStock,
            'current_stock' => (int) $product->quantity,
        ];
    }

    public function getStockLevelAtDate(int $productId, int $branchId, CarbonImmutable $date): int
    {
        $last = DB::table('stock_mutations')
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->where('created_at', '<=', $date)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($last) {
            $stockAfter = $last->stock_after ?? ($last->stock_before + $last->quantity_change);
            return (int) $stockAfter;
        }

        $product = Product::select('quantity')->find($productId);
        if (!$product) return 0;

        $netSinceDate = (int) DB::table('stock_mutations')
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->where('created_at', '>', $date)
            ->sum('quantity_change');

        return max(0, (int) $product->quantity - $netSinceDate);
    }
}
