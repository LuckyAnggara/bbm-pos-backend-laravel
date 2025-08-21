<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $branchId = $request->input('branch_id');
            $search = $request->input('search');
            $page = $request->input('page', 1);
            $limit = $request->input('limit', 10);

            $query = Supplier::query()
                ->when($branchId, function ($q) use ($branchId) {
                    return $q->where('branch_id', $branchId);
                })
                ->when($search, function ($q) use ($search) {
                    return $q->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('contact_person', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                });

            // Get suppliers with analytics
            $suppliers = $query->with(['purchaseOrders' => function ($q) {
                $q->select('supplier_id', 'total_amount', 'outstanding_amount', 'created_at');
            }])
                ->paginate($limit, ['*'], 'page', $page);

            // Add analytics to each supplier
            foreach ($suppliers->items() as $supplier) {
                $supplier->analytics = $this->calculateSupplierAnalytics($supplier);
            }

            return response()->json($suppliers);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data pemasok',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'contact_person' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string',
                'notes' => 'nullable|string',
                'branch_id' => 'required|exists:branches,id',
                // Extended fields
                'company_type' => 'nullable|in:individual,company',
                'tax_id' => 'nullable|string|max:50',
                'credit_limit' => 'nullable|numeric|min:0',
                'payment_terms' => 'nullable|string|max:100',
                'bank_name' => 'nullable|string|max:100',
                'bank_account_number' => 'nullable|string|max:50',
                'bank_account_name' => 'nullable|string|max:255',
                'website' => 'nullable|url|max:255',
                'industry' => 'nullable|string|max:100',
                'rating' => 'nullable|integer|min:1|max:5',
                'is_active' => 'boolean',
            ]);

            $supplier = Supplier::create($validated);
            $supplier->analytics = $this->calculateSupplierAnalytics($supplier);

            return response()->json($supplier, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Data tidak valid',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat pemasok',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $supplier = Supplier::with(['purchaseOrders' => function ($q) {
                $q->select('supplier_id', 'total_amount', 'outstanding_amount', 'created_at')
                    ->orderBy('created_at', 'desc');
            }])->findOrFail($id);

            $supplier->analytics = $this->calculateSupplierAnalytics($supplier);

            return response()->json($supplier);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Pemasok tidak ditemukan',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $supplier = Supplier::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'contact_person' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string',
                'notes' => 'nullable|string',
                'branch_id' => 'sometimes|required|exists:branches,id',
                // Extended fields
                'company_type' => 'nullable|in:individual,company',
                'tax_id' => 'nullable|string|max:50',
                'credit_limit' => 'nullable|numeric|min:0',
                'payment_terms' => 'nullable|string|max:100',
                'bank_name' => 'nullable|string|max:100',
                'bank_account_number' => 'nullable|string|max:50',
                'bank_account_name' => 'nullable|string|max:255',
                'website' => 'nullable|url|max:255',
                'industry' => 'nullable|string|max:100',
                'rating' => 'nullable|integer|min:1|max:5',
                'is_active' => 'boolean',
            ]);

            $supplier->update($validated);
            $supplier->refresh();
            $supplier->load(['purchaseOrders' => function ($q) {
                $q->select('supplier_id', 'total_amount', 'outstanding_amount', 'created_at');
            }]);
            $supplier->analytics = $this->calculateSupplierAnalytics($supplier);

            return response()->json($supplier);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Data tidak valid',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal memperbarui pemasok',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $supplier = Supplier::findOrFail($id);

            // Check if supplier has purchase orders
            if ($supplier->purchaseOrders()->count() > 0) {
                return response()->json([
                    'message' => 'Pemasok tidak dapat dihapus karena memiliki riwayat pesanan pembelian'
                ], 422);
            }

            $supplier->delete();

            return response()->json([
                'message' => 'Pemasok berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menghapus pemasok',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get supplier statistics for dashboard.
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $branchId = $request->input('branch_id');

            $stats = [
                'total_suppliers' => Supplier::when($branchId, function ($q) use ($branchId) {
                    return $q->where('branch_id', $branchId);
                })->count(),

                'active_suppliers' => Supplier::when($branchId, function ($q) use ($branchId) {
                    return $q->where('branch_id', $branchId);
                })->where('is_active', true)->count(),

                'total_outstanding' => PurchaseOrder::whereHas('supplier', function ($q) use ($branchId) {
                    if ($branchId) {
                        $q->where('branch_id', $branchId);
                    }
                })->sum('outstanding_amount'),

                'top_suppliers' => $this->getTopSuppliers($branchId),
            ];

            return response()->json($stats);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil statistik pemasok',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get top suppliers with detailed analytics.
     */
    public function topSuppliers(Request $request): JsonResponse
    {
        try {
            $branchId = $request->input('branch_id');
            $limit = $request->input('limit', 5);
            $months = $request->input('months', 12);

            // Calculate date range
            $dateFrom = now()->subMonths($months);

            // Get most frequent suppliers (by number of orders)
            $mostFrequent = Supplier::select('suppliers.*')
                ->selectRaw('COUNT(purchase_orders.id) as total_purchases')
                ->selectRaw('COALESCE(SUM(purchase_orders.total_amount), 0) as total_spent')
                ->leftJoin('purchase_orders', 'suppliers.id', '=', 'purchase_orders.supplier_id')
                ->when($branchId, function ($q) use ($branchId) {
                    return $q->where('suppliers.branch_id', $branchId);
                })
                ->where('purchase_orders.created_at', '>=', $dateFrom)
                ->groupBy('suppliers.id')
                ->orderByDesc('total_purchases')
                ->limit($limit)
                ->get();

            // Get highest spending suppliers (by total amount)
            $highestSpending = Supplier::select('suppliers.*')
                ->selectRaw('COUNT(purchase_orders.id) as total_purchases')
                ->selectRaw('COALESCE(SUM(purchase_orders.total_amount), 0) as total_spent')
                ->leftJoin('purchase_orders', 'suppliers.id', '=', 'purchase_orders.supplier_id')
                ->when($branchId, function ($q) use ($branchId) {
                    return $q->where('suppliers.branch_id', $branchId);
                })
                ->where('purchase_orders.created_at', '>=', $dateFrom)
                ->groupBy('suppliers.id')
                ->orderByDesc('total_spent')
                ->limit($limit)
                ->get();

            return response()->json([
                'most_frequent' => $mostFrequent,
                'highest_spending' => $highestSpending,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data pemasok teratas',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Calculate analytics for a supplier.
     */
    private function calculateSupplierAnalytics(Supplier $supplier): array
    {
        $orders = $supplier->purchaseOrders;

        return [
            'total_orders' => $orders->count(),
            'total_amount' => $orders->sum('total_amount'),
            'outstanding_amount' => $orders->sum('outstanding_amount'),
            'last_order_date' => $orders->max('created_at'),
            'average_order_value' => $orders->count() > 0 ? $orders->avg('total_amount') : 0,
            'payment_reliability' => $this->calculatePaymentReliability($orders),
        ];
    }

    /**
     * Calculate payment reliability score.
     */
    private function calculatePaymentReliability($orders): float
    {
        if ($orders->count() === 0) return 100.0;

        $totalOrders = $orders->count();
        $paidOrders = $orders->where('outstanding_amount', 0)->count();

        return ($paidOrders / $totalOrders) * 100;
    }

    /**
     * Get top suppliers by total transaction amount.
     */
    private function getTopSuppliers($branchId, $limit = 5): array
    {
        return Supplier::select('suppliers.*')
            ->selectRaw('COALESCE(SUM(purchase_orders.total_amount), 0) as total_amount')
            ->leftJoin('purchase_orders', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->when($branchId, function ($q) use ($branchId) {
                return $q->where('suppliers.branch_id', $branchId);
            })
            ->groupBy('suppliers.id')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
