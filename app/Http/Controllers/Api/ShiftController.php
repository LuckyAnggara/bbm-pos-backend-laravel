<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ShiftController extends Controller
{
    /**
     * Start a new shift for the authenticated user.
     */
    public function startShift(Request $request)
    {
        $validated = $request->validate([
            'starting_balance' => 'required|numeric|min:0',
            'branch_id' => 'required|exists:branches,id',
        ]);

        $user = auth()->user();

        // Cek apakah user sudah punya shift yang aktif
        $activeShift = Shift::where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if ($activeShift) {
            return response()->json(['message' => 'User already has an active shift.'], 409); // 409 Conflict
        }

        try {
            $shift = DB::transaction(function () use ($validated, $user) {
                return Shift::create([
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'branch_id' => $validated['branch_id'],
                    'start_shift' => Carbon::now(),
                    'starting_balance' => $validated['starting_balance'],
                    'status' => 'open',
                ]);
            });

            return response()->json($shift, 201);
        } catch (\Exception $e) {
            Log::error("Error starting shift for user {$user->id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to start shift.'], 500);
        }
    }

    /**
     * End the active shift for the authenticated user.
     */
    public function endShift(Request $request)
    {
        $user = auth()->user();
        $shift = Shift::where('user_id', $user->id)
            ->where('status', 'open')
            ->firstOrFail(); // Gagal jika tidak ada shift aktif

        try {
            DB::transaction(function () use ($shift) {
                $sales = $shift->sales(); // Mengambil query builder relasi sales

                // Kalkulasi total dari semua penjualan selama shift ini
                $totalSales = $sales->sum('total_amount');
                $totalCash = $sales->where('payment_method', 'cash')->sum('amount_paid');
                $totalBank = $sales->where('payment_method', 'bank')->sum('amount_paid');
                // ...tambahkan kalkulasi untuk metode pembayaran lain jika perlu

                $shift->update([
                    'end_shift' => Carbon::now(),
                    'status' => 'closed',
                    'total_sales' => $totalSales,
                    'total_cash_payments' => $totalCash,
                    'total_bank_payments' => $totalBank,
                    // ...update total lainnya
                ]);
            });

            return response()->json($shift->fresh()); // Ambil data terbaru dari DB
        } catch (\Exception $e) {
            Log::error("Error ending shift {$shift->id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to end shift.'], 500);
        }
    }

    /**
     * Get the currently active shift for the authenticated user.
     */
    public function getActiveShift()
    {
        $activeShift = Shift::where('user_id', auth()->id())
            ->where('status', 'open')
            ->first();

        if (!$activeShift) {
            return response()->json(['message' => 'No active shift found.'], 404);
        }

        return response()->json($activeShift);
    }

    /**
     * Display a listing of closed shifts.
     */
    public function index(Request $request)
    {
        $limit = $request->input('limit', 15);
        $query = Shift::where('status', 'closed')->with('user:id,name', 'branch:id,name');

        // Tambahkan filter jika perlu, misalnya berdasarkan tanggal
        if ($request->filled('date')) {
            $query->whereDate('start_shift', $request->date);
        }

        $shifts = $query->latest()->paginate($limit);
        return response()->json($shifts);
    }
}
