<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMutation;
use App\Models\StockMutationReport;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class StockMutationReportService
{
    /**
     * Compute report items for a branch between start and end (inclusive) and optionally persist.
     *
     * @return array{items: array<int, array<string,mixed>>, saved: bool}
     */
    public function compute(int $branchId, CarbonImmutable $startDate, CarbonImmutable $endDate, bool $persist = true): array
    {
        $products = Product::where('branch_id', $branchId)
            ->select('id', 'name', 'sku', 'category_name', 'quantity')
            ->get();

        $items = [];
        $dayBeforeStart = $startDate->subDay()->endOfDay();

        foreach ($products as $product) {
            $initialStock = $this->getStockLevelAtDate($product->id, $branchId, $dayBeforeStart);

            $mutations = StockMutation::where('branch_id', $branchId)
                ->where('product_id', $product->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get(['type', 'quantity_change']);

            $stockInFromPO = 0;
            $stockSold = 0;
            $stockReturned = 0;

            foreach ($mutations as $m) {
                $type = strtolower((string) $m->type);
                if ($type === 'purchase' || $type === 'purchase_receipt') {
                    $stockInFromPO += (int) $m->quantity_change;
                } elseif ($type === 'sale') {
                    $stockSold += abs((int) $m->quantity_change);
                } elseif (in_array($type, ['sale_return', 'transaction_deleted_sale_restock', 'return'])) {
                    $qty = (int) $m->quantity_change;
                    $stockReturned += $qty > 0 ? $qty : abs($qty);
                }
            }

            if ($stockSold === 0) {
                $stockSoldFromSales = (int) DB::table('sale_details')
                    ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
                    ->where('sales.branch_id', $branchId)
                    ->whereBetween('sales.created_at', [$startDate, $endDate])
                    ->where('sales.status', 'completed')
                    ->where('sale_details.product_id', $product->id)
                    ->sum('sale_details.quantity');
                $stockSold = $stockSoldFromSales;
            }

            $finalStockCalculated = $initialStock + $stockInFromPO - $stockSold + $stockReturned;

            $items[] = [
                'productId' => (string) $product->id,
                'productName' => $product->name,
                'sku' => $product->sku,
                'categoryName' => $product->category_name,
                'initialStock' => $initialStock,
                'stockInFromPO' => $stockInFromPO,
                'stockSold' => $stockSold,
                'stockReturned' => $stockReturned,
                'finalStockCalculated' => $finalStockCalculated,
                'currentLiveStock' => (int) $product->quantity,
            ];
        }

        $saved = false;
        if ($persist) {
            StockMutationReport::updateOrCreate(
                [
                    'branch_id' => $branchId,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
                [
                    'data' => $items,
                    'generated_at' => Carbon::now(),
                ]
            );
            $saved = true;
        }

        return ['items' => $items, 'saved' => $saved];
    }

    public function getStockLevelAtDate(int $productId, int $branchId, CarbonImmutable $date): int
    {
        $last = StockMutation::where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->where('created_at', '<=', $date)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($last) {
            return (int) ($last->stock_after ?? ($last->stock_before + $last->quantity_change));
        }

        $product = Product::select('quantity')->find($productId);
        if (! $product) {
            return 0;
        }

        $netSinceDate = (int) StockMutation::where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->where('created_at', '>', $date)
            ->sum('quantity_change');

        return max(0, (int) $product->quantity - $netSinceDate);
    }
}
