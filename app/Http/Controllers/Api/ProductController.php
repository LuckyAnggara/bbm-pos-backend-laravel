<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource with pagination, search, and filtering.
     */
    public function index(Request $request)
    {
        try {
            $limit = $request->input('limit', 15);
            $search = $request->input('search');
            $categoryId = $request->input('category_id');
            $branchId = $request->input('branch_id');
            $query = Product::with(['category', 'branch']);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            }

            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            $products = $query->latest()->paginate($limit);

            return response()->json($products);
        } catch (\Exception $e) {
            Log::error('Error fetching products: ' . $e->getMessage());

            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255|unique:products,sku',
            'quantity' => 'required|integer|min:0',
            'cost_price' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'branch_id' => 'required|exists:branches,id',
            'image_url' => 'nullable|string|max:255',
            'image_hint' => 'nullable|string|max:255',
        ]);

        try {
            $product = DB::transaction(function () use ($validated) {
                // Ambil nama kategori untuk konsistensi data
                $category = Category::find($validated['category_id']);
                $validated['category_name'] = $category->name;

                return Product::create($validated);
            });

            return response()->json($product, 201);
        } catch (\Exception $e) {
            Log::error('Error creating product: ' . $e->getMessage());

            return response()->json(['message' => 'Failed to create product. Please try again.'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        return response()->json($product->load(['category', 'branch']));
    }

    /**
     * Get detailed product information with transactions, insights, and mutations
     */
    public function details(Product $product)
    {
        try {
            // Load basic product information
            $product->load(['category', 'branch']);

            return response()->json($product);
        } catch (\Exception $e) {
            Log::error('Error fetching product details: ' . $e->getMessage());

            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Get product transaction history
     */
    public function transactions(Request $request, Product $product)
    {
        try {
            $limit = $request->input('limit', 20);
            $type = $request->input('type'); // 'sale', 'purchase', 'all'
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            $transactions = collect();

            // Get sales transactions using SaleDetail model
            if (! $type || $type === 'sale' || $type === 'all') {
                $sales = DB::table('sale_details')
                    ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
                    ->join('users', 'sales.user_id', '=', 'users.id')
                    ->where('sale_details.product_id', $product->id)
                    ->select([
                        'sales.id as transaction_id',
                        'sales.transaction_number as reference',
                        'sale_details.quantity',
                        'sale_details.price_at_sale as price',
                        DB::raw('sale_details.quantity * sale_details.price_at_sale as total'),
                        'sales.created_at',
                        'sales.user_name',
                        DB::raw("'sale' as type"),
                        DB::raw("'Penjualan' as type_label"),
                    ]);

                if ($startDate) {
                    $sales->whereDate('sales.created_at', '>=', $startDate);
                }
                if ($endDate) {
                    $sales->whereDate('sales.created_at', '<=', $endDate);
                }

                $transactions = $transactions->merge($sales->get());
            }

            // Get purchase transactions using PurchaseOrderDetail model
            if (! $type || $type === 'purchase' || $type === 'all') {
                $purchases = DB::table('purchase_order_details')
                    ->join('purchase_orders', 'purchase_order_details.purchase_order_id', '=', 'purchase_orders.id')
                    ->join('users', 'purchase_orders.user_id', '=', 'users.id')
                    ->where('purchase_order_details.product_id', $product->id)
                    ->select([
                        'purchase_orders.id as transaction_id',
                        'purchase_orders.po_number as reference',
                        'purchase_order_details.ordered_quantity as quantity',
                        'purchase_order_details.purchase_price as price',
                        DB::raw('purchase_order_details.ordered_quantity * purchase_order_details.purchase_price as total'),
                        'purchase_orders.created_at',
                        'users.name as user_name',
                        DB::raw("'purchase' as type"),
                        DB::raw("'Pembelian' as type_label"),
                    ]);

                if ($startDate) {
                    $purchases->whereDate('purchase_orders.created_at', '>=', $startDate);
                }
                if ($endDate) {
                    $purchases->whereDate('purchase_orders.created_at', '<=', $endDate);
                }

                $transactions = $transactions->merge($purchases->get());
            }

            // Sort by date descending
            $transactions = $transactions->sortByDesc('created_at')->values();

            // Paginate manually
            $total = $transactions->count();
            $page = $request->input('page', 1);
            $offset = ($page - 1) * $limit;
            $paginatedTransactions = $transactions->slice($offset, $limit)->values();

            return response()->json([
                'data' => $paginatedTransactions,
                'total' => $total,
                'per_page' => $limit,
                'current_page' => $page,
                'last_page' => ceil($total / $limit),
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching product transactions: ' . $e->getMessage());

            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Get product insights and analytics
     */
    public function insights(Request $request, Product $product)
    {
        try {
            $months = $request->input('months', 12); // Default 12 months

            // Calculate date range
            $endDate = now();
            $startDate = now()->subMonths($months);

            // Sales trend (monthly)
            $salesTrend = DB::table('sale_details')
                ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
                ->where('sale_details.product_id', $product->id)
                ->whereDate('sales.created_at', '>=', $startDate)
                ->select([
                    DB::raw('YEAR(sales.created_at) as year'),
                    DB::raw('MONTH(sales.created_at) as month'),
                    DB::raw('SUM(sale_details.quantity) as quantity_sold'),
                    DB::raw('SUM(sale_details.quantity * sale_details.price_at_sale) as revenue'),
                    DB::raw('COUNT(DISTINCT sales.id) as transaction_count'),
                ])
                ->groupBy(['year', 'month'])
                ->orderBy('year')
                ->orderBy('month')
                ->get();

            // Total statistics
            $totalSales = DB::table('sale_details')
                ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
                ->where('sale_details.product_id', $product->id)
                ->whereDate('sales.created_at', '>=', $startDate)
                ->sum('sale_details.quantity');

            $totalRevenue = DB::table('sale_details')
                ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
                ->where('sale_details.product_id', $product->id)
                ->whereDate('sales.created_at', '>=', $startDate)
                ->sum(DB::raw('sale_details.quantity * sale_details.price_at_sale'));

            $averagePrice = DB::table('sale_details')
                ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
                ->where('sale_details.product_id', $product->id)
                ->whereDate('sales.created_at', '>=', $startDate)
                ->avg('sale_details.price_at_sale');

            // Best selling day
            $bestDay = DB::table('sale_details')
                ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
                ->where('sale_details.product_id', $product->id)
                ->whereDate('sales.created_at', '>=', $startDate)
                ->select([
                    DB::raw('DATE(sales.created_at) as date'),
                    DB::raw('SUM(sale_details.quantity) as quantity_sold'),
                ])
                ->groupBy('date')
                ->orderByDesc('quantity_sold')
                ->first();

            return response()->json([
                'sales_trend' => $salesTrend,
                'statistics' => [
                    'total_sales' => (int) $totalSales,
                    'total_revenue' => (float) $totalRevenue,
                    'average_price' => (float) $averagePrice,
                    'best_selling_day' => $bestDay,
                ],
                'period' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'months' => $months,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching product insights: ' . $e->getMessage());

            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Get product stock mutations
     */
    public function mutations(Request $request, Product $product)
    {
        try {
            $limit = $request->input('limit', 20);
            $type = $request->input('type'); // 'adjustment', 'sale', 'purchase', 'all'
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            $query = StockMutation::where('product_id', $product->id);

            if ($type && $type !== 'all') {
                $query->where('type', $type);
            }

            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }

            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            $mutations = $query->select([
                'id',
                'quantity_change',
                'stock_before',
                'stock_after',
                'type',
                'description',
                'reference_type',
                'reference_id',
                'user_name',
                'created_at',
            ])
                ->orderByDesc('created_at')
                ->paginate($limit);

            return response()->json($mutations);
        } catch (\Exception $e) {
            Log::error('Error fetching product mutations: ' . $e->getMessage());

            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'sku' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('products')->ignore($product->id)],
            'quantity' => 'sometimes|required|integer|min:0',
            'cost_price' => 'sometimes|required|numeric|min:0',
            'price' => 'sometimes|required|numeric|min:0',
            'category_id' => 'sometimes|required|exists:categories,id',
            'branch_id' => 'sometimes|required|exists:branches,id',
            'image_url' => 'nullable|string|max:255',
            'image_hint' => 'nullable|string|max:255',
        ]);

        try {
            DB::transaction(function () use ($product, $validated, $request) {
                // Jika kategori diubah, update juga category_name
                if ($request->has('category_id')) {
                    $category = Category::find($validated['category_id']);
                    $validated['category_name'] = $category->name;
                }
                $product->update($validated);
            });

            return response()->json($product);
        } catch (\Exception $e) {
            Log::error("Error updating product {$product->id}: " . $e->getMessage());

            return response()->json(['message' => 'Failed to update product. Please try again.'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        try {
            // Kita bisa tambahkan pengecekan di sini, misal:
            // "Tidak bisa hapus produk jika pernah ada transaksi"
            // Namun untuk saat ini, kita langsung hapus.
            $product->delete();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error("Error deleting product {$product->id}: " . $e->getMessage());

            return response()->json(['message' => 'Failed to delete product. Please try again.'], 500);
        }
    }
}
