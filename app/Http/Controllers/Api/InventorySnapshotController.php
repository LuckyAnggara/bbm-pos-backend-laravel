<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryStockSnapshot;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventorySnapshotController extends Controller
{
    /**
     * Overall yearly snapshot aggregation (all branches merged)
     */
    public function yearStatus(Request $request)
    {
        $data = InventoryStockSnapshot::select('year', 'type', DB::raw('COUNT(*) as snapshot_count'), DB::raw('SUM(quantity*COALESCE(cost_price,0)) as total_value'))
            ->groupBy('year', 'type')
            ->orderBy('year', 'desc')
            ->get()
            ->groupBy('year')
            ->map(function ($group) {
                $closing = $group->firstWhere('type', 'closing');
                $opening = $group->firstWhere('type', 'opening');
                return [
                    'closing' => $closing->snapshot_count ?? 0,
                    'closing_value' => $closing->total_value ?? 0,
                    'opening' => $opening->snapshot_count ?? 0,
                    'opening_value' => $opening->total_value ?? 0,
                ];
            });
        return response()->json(['data' => $data]);
    }

    /**
     * Per-branch status for a given base year (closing year) and its following opening year.
     * Query param: year (defaults current year)
     * Returns per branch counts + done flags.
     */
    public function branchStatus(Request $request)
    {
        $year = (int)($request->query('year') ?: now()->year);
        $openingYear = $year + 1;

        // preload products count per branch
        $productCounts = DB::table('products')
            ->select('branch_id', DB::raw('COUNT(*) as total_products'))
            ->groupBy('branch_id')
            ->pluck('total_products', 'branch_id');

        // closing counts per branch for base year
        $closingCounts = InventoryStockSnapshot::select('branch_id', DB::raw('COUNT(*) as c'))
            ->where('year', $year)->where('type', 'closing')
            ->groupBy('branch_id')->pluck('c', 'branch_id');
        // opening counts per branch for opening year
        $openingCounts = InventoryStockSnapshot::select('branch_id', DB::raw('COUNT(*) as c'))
            ->where('year', $openingYear)->where('type', 'opening')
            ->groupBy('branch_id')->pluck('c', 'branch_id');

        // values per branch (optional aggregates)
        $closingValues = InventoryStockSnapshot::select('branch_id', DB::raw('SUM(quantity*COALESCE(cost_price,0)) as v'))
            ->where('year', $year)->where('type', 'closing')
            ->groupBy('branch_id')->pluck('v', 'branch_id');
        $openingValues = InventoryStockSnapshot::select('branch_id', DB::raw('SUM(quantity*COALESCE(cost_price,0)) as v'))
            ->where('year', $openingYear)->where('type', 'opening')
            ->groupBy('branch_id')->pluck('v', 'branch_id');

        $branches = DB::table('branches')->select('id', 'name')->orderBy('name')->get();
        $data = [];
        foreach ($branches as $b) {
            $totalProducts = (int)($productCounts[$b->id] ?? 0);
            $cCount = (int)($closingCounts[$b->id] ?? 0);
            $oCount = (int)($openingCounts[$b->id] ?? 0);
            $data[] = [
                'branch_id' => $b->id,
                'branch_name' => $b->name,
                'total_products' => $totalProducts,
                'closing_count' => $cCount,
                'closing_done' => $totalProducts > 0 ? $cCount === $totalProducts : false,
                'closing_value' => (float)($closingValues[$b->id] ?? 0),
                'opening_count' => $oCount,
                'opening_done' => $totalProducts > 0 ? $oCount === $totalProducts : false,
                'opening_value' => (float)($openingValues[$b->id] ?? 0),
            ];
        }
        return response()->json(['data' => $data, 'year' => $year, 'opening_year' => $openingYear]);
    }

    public function closeYear(Request $request)
    {
        $user = $request->user();
        $year = (int)($request->input('year') ?: now()->year);
        $force = (bool)$request->input('force', false);

        $exists = InventoryStockSnapshot::where('year', $year)->where('type', 'closing')->exists();
        if ($exists && !$force) {
            return response()->json(['message' => 'Closing snapshot already exists for year ' . $year], 409);
        }
        if ($exists && $force) {
            InventoryStockSnapshot::where('year', $year)->where('type', 'closing')->delete();
        }

        $hasValueAmount = Schema::hasColumn('inventory_stock_snapshots', 'value_amount');
        DB::transaction(function () use ($year, $user, $hasValueAmount) {
            Product::chunk(500, function ($products) use ($year, $user, $hasValueAmount) {
                $insert = [];
                foreach ($products as $p) {
                    $insert[] = [
                        'product_id' => $p->id,
                        'branch_id' => $p->branch_id,
                        'year' => $year,
                        'type' => 'closing',
                        'quantity' => $p->quantity,
                        'cost_price' => $p->cost_price,
                        // include value_amount only if column exists
                        ...($hasValueAmount ? ['value_amount' => ($p->quantity ?? 0) * ($p->cost_price ?? 0)] : []),
                        'created_by' => $user->id,
                        'created_by_name' => $user->name ?? 'Admin',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (!empty($insert)) {
                    InventoryStockSnapshot::insert($insert);
                }
            });
        });
        return response()->json(['message' => 'Closing snapshot created for year ' . $year]);
    }

    /**
     * Close year for a single branch (subset of products)
     */
    public function closeYearBranch(Request $request)
    {
        $user = $request->user();
        $year = (int)($request->input('year') ?: now()->year);
        $branchId = (int)$request->input('branch_id');
        $force = (bool)$request->input('force', false);
        if (!$branchId) {
            return response()->json(['message' => 'branch_id required'], 422);
        }
        $exists = InventoryStockSnapshot::where('year', $year)->where('type', 'closing')->where('branch_id', $branchId)->exists();
        if ($exists && !$force) {
            return response()->json(['message' => 'Closing snapshot already exists for branch ' . $branchId . ' year ' . $year], 409);
        }
        if ($exists && $force) {
            InventoryStockSnapshot::where('year', $year)->where('type', 'closing')->where('branch_id', $branchId)->delete();
        }
        $hasValueAmount = Schema::hasColumn('inventory_stock_snapshots', 'value_amount');
        DB::transaction(function () use ($year, $user, $branchId, $hasValueAmount) {
            Product::where('branch_id', $branchId)->chunk(500, function ($products) use ($year, $user, $hasValueAmount) {
                $insert = [];
                foreach ($products as $p) {
                    $insert[] = [
                        'product_id' => $p->id,
                        'branch_id' => $p->branch_id,
                        'year' => $year,
                        'type' => 'closing',
                        'quantity' => $p->quantity,
                        'cost_price' => $p->cost_price,
                        ...($hasValueAmount ? ['value_amount' => ($p->quantity ?? 0) * ($p->cost_price ?? 0)] : []),
                        'created_by' => $user->id,
                        'created_by_name' => $user->name ?? 'Admin',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (!empty($insert)) {
                    InventoryStockSnapshot::insert($insert);
                }
            });
        });
        return response()->json(['message' => 'Closing snapshot created for branch ' . $branchId . ' year ' . $year]);
    }

    public function openYear(Request $request)
    {
        $user = $request->user();
        $year = (int)$request->input('year');
        if (!$year) {
            return response()->json(['message' => 'Year required'], 422);
        }
        $prev = $year - 1;
        $prevExists = InventoryStockSnapshot::where('year', $prev)->where('type', 'closing')->exists();
        if (!$prevExists) {
            return response()->json(['message' => 'Closing snapshot for previous year ' . $prev . ' not found'], 409);
        }
        $openingExists = InventoryStockSnapshot::where('year', $year)->where('type', 'opening')->exists();
        if ($openingExists) {
            return response()->json(['message' => 'Opening snapshot already exists for year ' . $year], 409);
        }

        $hasValueAmount = Schema::hasColumn('inventory_stock_snapshots', 'value_amount');
        DB::transaction(function () use ($year, $prev, $user, $hasValueAmount) {
            InventoryStockSnapshot::where('year', $year)->where('type', 'opening')->delete();
            $prevSnapshots = InventoryStockSnapshot::where('year', $prev)->where('type', 'closing')->orderBy('id');
            $prevSnapshots->chunk(500, function ($rows) use ($year, $user, $hasValueAmount) {
                $insert = [];
                foreach ($rows as $row) {
                    $insert[] = [
                        'product_id' => $row->product_id,
                        'branch_id' => $row->branch_id,
                        'year' => $year,
                        'type' => 'opening',
                        'quantity' => $row->quantity,
                        'cost_price' => $row->cost_price,
                        ...($hasValueAmount ? ['value_amount' => ($row->quantity ?? 0) * ($row->cost_price ?? 0)] : []),
                        'created_by' => $user->id,
                        'created_by_name' => $user->name ?? 'Admin',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if ($insert) {
                    InventoryStockSnapshot::insert($insert);
                }
            });
        });

        return response()->json(['message' => 'Opening snapshot created for year ' . $year]);
    }

    /**
     * Open year (create opening snapshot) for a single branch.
     */
    public function openYearBranch(Request $request)
    {
        $user = $request->user();
        $year = (int)$request->input('year');
        $branchId = (int)$request->input('branch_id');
        if (!$year || !$branchId) {
            return response()->json(['message' => 'year and branch_id required'], 422);
        }
        $prev = $year - 1;
        $prevExists = InventoryStockSnapshot::where('year', $prev)->where('type', 'closing')->where('branch_id', $branchId)->exists();
        if (!$prevExists) {
            return response()->json(['message' => 'Closing snapshot for branch ' . $branchId . ' previous year ' . $prev . ' not found'], 409);
        }
        $openingExists = InventoryStockSnapshot::where('year', $year)->where('type', 'opening')->where('branch_id', $branchId)->exists();
        if ($openingExists) {
            return response()->json(['message' => 'Opening snapshot already exists for branch ' . $branchId . ' year ' . $year], 409);
        }
        $hasValueAmount = Schema::hasColumn('inventory_stock_snapshots', 'value_amount');
        DB::transaction(function () use ($year, $prev, $user, $branchId, $hasValueAmount) {
            InventoryStockSnapshot::where('year', $year)->where('type', 'opening')->where('branch_id', $branchId)->delete();
            $prevSnapshots = InventoryStockSnapshot::where('year', $prev)->where('type', 'closing')->where('branch_id', $branchId)->orderBy('id');
            $prevSnapshots->chunk(500, function ($rows) use ($year, $user, $hasValueAmount) {
                $insert = [];
                foreach ($rows as $row) {
                    $insert[] = [
                        'product_id' => $row->product_id,
                        'branch_id' => $row->branch_id,
                        'year' => $year,
                        'type' => 'opening',
                        'quantity' => $row->quantity,
                        'cost_price' => $row->cost_price,
                        ...($hasValueAmount ? ['value_amount' => ($row->quantity ?? 0) * ($row->cost_price ?? 0)] : []),
                        'created_by' => $user->id,
                        'created_by_name' => $user->name ?? 'Admin',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if ($insert) {
                    InventoryStockSnapshot::insert($insert);
                }
            });
        });
        return response()->json(['message' => 'Opening snapshot created for branch ' . $branchId . ' year ' . $year]);
    }

    public function closingDetail(Request $request, int $year)
    {
        $branchId = $request->query('branch_id');
        $type = $request->query('type', 'closing');
        $query = InventoryStockSnapshot::with('product')
            ->where('year', $year)
            ->where('type', $type);
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        $items = $query->orderBy('branch_id')->orderBy('product_id')->get()->map(function ($row) {
            return [
                'product_id' => $row->product_id,
                'product_name' => $row->product?->name,
                'branch_id' => $row->branch_id,
                'quantity' => (float)$row->quantity,
                'cost_price' => (float)($row->cost_price ?? 0),
                'value_amount' => (float)($row->value_amount ?? (($row->quantity ?? 0) * ($row->cost_price ?? 0)))
            ];
        });
        return response()->json(['data' => $items]);
    }

    public function exportClosingCsv(Request $request, int $year)
    {
        $branchId = $request->query('branch_id');
        $type = $request->query('type', 'closing');
        $query = InventoryStockSnapshot::with('product')
            ->where('year', $year)->where('type', $type);
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        $rows = $query->orderBy('branch_id')->orderBy('product_id')->get();
        $csvLines = [];
        $csvLines[] = 'product_id,product_name,branch_id,quantity,cost_price,value_amount';
        foreach ($rows as $r) {
            $csvLines[] = implode(',', [
                $r->product_id,
                '"' . str_replace('"', '""', $r->product?->name) . '"',
                $r->branch_id,
                (float)$r->quantity,
                (float)($r->cost_price ?? 0),
                (float)(($r->value_amount ?? (($r->quantity ?? 0) * ($r->cost_price ?? 0))))
            ]);
        }
        $content = implode("\n", $csvLines);
        $filename = "inventory_{$type}_{$year}" . ($branchId ? "_branch{$branchId}" : '') . '.csv';
        return response($content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=' . $filename
        ]);
    }
}
