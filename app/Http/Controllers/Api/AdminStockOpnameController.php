<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockOpnameSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminStockOpnameController extends Controller
{
    /**
     * List all stock opname sessions for admin review - can see all branches
     */
    public function index(Request $request)
    {
        try {
            // Only admin can access this endpoint
            if (auth()->user()->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
            }

            $limit = $request->input('per_page', 25);
            $search = $request->input('search');
            $status = $request->input('status', 'SUBMIT'); // Default to SUBMIT status for review
            $branchId = $request->input('branch_id');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            $query = StockOpnameSession::with([
                'creator:id,name',
                'approver:id,name',
                'submitter:id,name',
                'branch:id,name',
            ]);

            // Filter by status
            if ($status && $status !== 'all') {
                $query->where('status', $status);
            }

            // Filter by branch
            if ($branchId && $branchId !== 'all') {
                $query->where('branch_id', $branchId);
            }

            // Filter by date range
            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            // Search by code, notes, or creator name
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('creator', function ($subQuery) use ($search) {
                            $subQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('branch', function ($subQuery) use ($search) {
                            $subQuery->where('name', 'like', "%{$search}%");
                        });
                });
            }

            $sessions = $query->orderByDesc('created_at')->paginate($limit);

            return response()->json($sessions);
        } catch (\Exception $e) {
            Log::error('Error fetching stock opname sessions for admin review: '.$e->getMessage());

            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Get detailed view of a stock opname session for admin review
     */
    public function show(StockOpnameSession $session)
    {
        try {
            // Only admin can access this endpoint
            if (auth()->user()->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
            }

            $session->load([
                'items',
                'creator:id,name',
                'approver:id,name',
                'submitter:id,name',
                'branch:id,name',
            ]);

            return response()->json($session);
        } catch (\Exception $e) {
            Log::error('Error fetching stock opname session details: '.$e->getMessage());

            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Approve a stock opname session - Admin only
     */
    public function approve(Request $request, StockOpnameSession $session)
    {
        try {
            // Only admin can approve
            if (auth()->user()->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
            }

            // Check if session is in SUBMIT status
            if ($session->status !== 'SUBMIT') {
                return response()->json(['message' => 'Stock opname can only be approved when in SUBMIT status'], 400);
            }

            // Use the approve method from StockOpnameController logic
            $stockOpnameController = new \App\Http\Controllers\StockOpnameController;

            return $stockOpnameController->approve($request, $session);
        } catch (\Exception $e) {
            Log::error('Error approving stock opname session: '.$e->getMessage());

            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Reject a stock opname session - Admin only
     */
    public function reject(Request $request, StockOpnameSession $session)
    {
        try {
            // Only admin can reject
            if (auth()->user()->role !== 'admin') {
                return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
            }

            // Check if session is in SUBMIT status
            if ($session->status !== 'SUBMIT') {
                return response()->json(['message' => 'Stock opname can only be rejected when in SUBMIT status'], 400);
            }

            // Use the reject method from StockOpnameController logic
            $stockOpnameController = new \App\Http\Controllers\StockOpnameController;

            return $stockOpnameController->reject($request, $session);
        } catch (\Exception $e) {
            Log::error('Error rejecting stock opname session: '.$e->getMessage());

            return response()->json(['message' => 'Internal Server Error'], 500);
        }
    }
}
