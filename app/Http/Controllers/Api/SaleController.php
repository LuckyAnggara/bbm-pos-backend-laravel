<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class SaleController extends Controller
{
    /**
     * Display a listing of sales with pagination and filtering.
     */
    public function index(Request $request)
    {
        try {
            $limit = $request->input('limit', 20);

            $query = Sale::with(['user:id,name', 'customer:id,name', 'branch:id,name']);

            // Filter by date range
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $start = Carbon::parse($request->start_date)->startOfDay();
                $end = Carbon::parse($request->end_date)->endOfDay();
                $query->whereBetween('created_at', [$start, $end]);
            }

            // Filter by payment_status_term
            if ($request->filled('payment_status_term')) {
                if ($request->payment_status_term === 'all') {
                    $query->whereNotNull('is_credit_sale');
                } else if ($request->payment_status_term === 'cash') {
                    $query->where('is_credit_sale', false);
                } else if ($request->payment_status_term === 'credit') {
                    $query->where('is_credit_sale', true);
                }
            }

            // Filter by branch
            if ($request->filled('branch_id')) {
                if ($request->branch_id === 'all') {
                    $query->whereNotNull('branch_id');
                } else {
                    $query->where('branch_id', $request->branch_id);
                }
            }

            // Filter by status
            if ($request->filled('status')) {
                if ($request->status === 'all') {
                    // keep all
                } else {
                    $query->where('status', $request->status);
                }
            }

            // Filter by payment status
            if ($request->filled('payment_status')) {
                if ($request->payment_status === 'all') {
                    // no-op
                } else {
                    $query->where('payment_status', $request->payment_status);
                }
            }

            // Filter has_outstanding
            if ($request->filled('has_outstanding') && $request->boolean('has_outstanding')) {
                $query->where('outstanding_amount', '>', 0);
            }

            // Only credit sales if requested
            // if ($request->filled('is_credit_sale')) {
            //     $query->where('is_credit_sale', true);
            // }

            if ($request->filled('shift_id')) {
                $query->where('shift_id', $request->shift_id);
            }

            // Search by transaction number or customer name
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('transaction_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%");
                });
            }

            $sales = $query->latest()->paginate($limit);

            return response()->json($sales);
        } catch (\Exception $e) {
            Log::error('Error fetching sales: ' . $e->getMessage());
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Display the specified sale along with its details.
     */
    public function show(Sale $sale)
    {
        // Load all related data for a complete view of the transaction
        return $sale->load([
            'user:id,name,email',
            'customer',
            'branch',
            // Load sale details, and for each detail, load the product info
            'saleDetails.product:id,name,sku',
            'customerPayments'
        ]);
    }


    /**
     * Display a listing of request retur / delete Admin.
     */
    public function listRequest(Request $request)
    {
        try {
            $limit = $request->input('limit', 20);
            $query = Sale::with(['user:id,name', 'customer:id,name', 'branch:id,name']);

            // Filter by date range
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            }
            // Filter by payment branch
            if ($request->filled('branch_id')) {
                if ($request->branch_id === 'all') {
                    $query->whereNotNull('branch_id');
                } else {
                    $query->where('branch_id', $request->branch_id);
                }
            }
            // Filter by status
            if ($request->filled('status')) {
                if ($request->status === 'all') {
                    $query->whereIn('status', ['pending_return', 'pending_void']);
                } else {
                    $query->where('status', $request->status);
                }
            }


            // Search by transaction number or customer name
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('transaction_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%");
                });
            }

            $sales = $query->latest()->paginate($limit);

            return response()->json($sales);
        } catch (\Exception $e) {
            Log::error('Error fetching sales: ' . $e->getMessage());
            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    // Metode untuk kasir MENGURAIKAN permintaan retur/pembatalan
    public function requestAction(Request $request, Sale $sale)
    {
        $validated = $request->validate([
            'action_type' => 'required|in:return,void',
            'reason' => 'required|string|max:255',
        ]);

        if ($sale->status !== 'completed') {
            return response()->json(['message' => 'Hanya transaksi yang sudah selesai yang bisa diproses.'], 422);
        }

        // Ubah status menjadi 'pending'
        $newStatus = $validated['action_type'] === 'return' ? 'pending_return' : 'pending_void';

        $sale->update([
            'status' => $newStatus,
            'returned_reason' => $validated['reason'], // Kita gunakan kolom yang sama untuk alasan
            'returned_by_user_id' => auth()->id(),
        ]);

        return response()->json($sale);
    }


    // Metode untuk admin MENYETUJUI permintaan
    public function approveAction(Sale $sale)
    {
        // Otorisasi: Hanya admin yang bisa menyetujui
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Hanya admin yang dapat menyetujui aksi ini.'], 403);
        }

        if (!in_array($sale->status, ['pending_return', 'pending_void'])) {
            return response()->json(['message' => 'Tidak ada permintaan yang menunggu persetujuan untuk transaksi ini.'], 422);
        }

        try {
            DB::transaction(function () use ($sale) {
                // Kembalikan stok untuk setiap item di transaksi
                foreach ($sale->saleDetails as $detail) {
                    $product = Product::find($detail->product_id);
                    if ($product) {
                        $stockBefore = $product->quantity;
                        $product->increment('quantity', $detail->quantity); // Stok kembali

                        StockMutation::create([
                            'branch_id' => $sale->branch_id,
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'quantity_change' => $detail->quantity, // Positif
                            'stock_before' => $stockBefore,
                            'stock_after' => $product->fresh()->quantity,
                            'type' => $sale->status === 'pending_return' ? 'sale_return' : 'sale_void',
                            'description' => "Stok dikembalikan dari {$sale->status} #{$sale->transaction_number}",
                            'reference_type' => Sale::class,
                            'reference_id' => $sale->id,
                            'user_id' => auth()->id(),
                            'user_name' => auth()->user()->name,
                        ]);
                    }
                }

                // Finalisasi status transaksi
                $finalStatus = $sale->status === 'pending_return' ? 'returned' : 'voided';
                $sale->update([
                    'status' => $finalStatus,
                    'returned_at' => now(),
                ]);
            });

            return response()->json($sale->fresh());
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memproses persetujuan: ' . $e->getMessage()], 500);
        }
    }


    // Metode untuk admin MENOLAK permintaan
    public function rejectAction(Sale $sale)
    {
        // Otorisasi: Hanya admin yang bisa menolak
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Hanya admin yang dapat menolak aksi ini.'], 403);
        }

        if (!in_array($sale->status, ['pending_return', 'pending_void'])) {
            return response()->json(['message' => 'Tidak ada permintaan yang menunggu penolakan untuk transaksi ini.'], 422);
        }

        try {
            DB::transaction(function () use ($sale) {
                // Finalisasi status transaksi
                $sale->update([
                    'status' => 'completed',
                    'rejected_at' => now(),
                ]);
            });

            return response()->json($sale->fresh());
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal memproses penolakan: ' . $e->getMessage()], 500);
        }
    }
}
