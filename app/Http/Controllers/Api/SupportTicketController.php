<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    /**
     * Display a listing of support tickets.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = $request->get('per_page', 15);
        $status = $request->get('status');
        $priority = $request->get('priority');

        $query = SupportTicket::with(['tenant', 'user', 'assignedUser']);

        // Super admins can see all tickets, tenant users only see their tenant's tickets
        if (!$user->isSuperAdmin()) {
            $query->where('tenant_id', $user->tenant_id);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($priority) {
            $query->where('priority', $priority);
        }

        $tickets = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($tickets);
    }

    /**
     * Store a newly created support ticket.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|string|in:low,medium,high,urgent',
        ]);

        $ticket = SupportTicket::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'status' => 'open',
        ]);

        return response()->json([
            'message' => 'Support ticket created successfully',
            'ticket' => $ticket->load(['tenant', 'user'])
        ], 201);
    }

    /**
     * Display the specified support ticket.
     */
    public function show(SupportTicket $ticket, Request $request)
    {
        $user = $request->user();

        // Check permissions
        if (!$user->isSuperAdmin() && $ticket->tenant_id !== $user->tenant_id) {
            return response()->json([
                'message' => 'Unauthorized to view this ticket'
            ], 403);
        }

        return response()->json($ticket->load(['tenant', 'user', 'assignedUser']));
    }

    /**
     * Update the specified support ticket.
     */
    public function update(Request $request, SupportTicket $ticket)
    {
        $user = $request->user();

        // Check permissions
        if (!$user->isSuperAdmin() && $ticket->tenant_id !== $user->tenant_id) {
            return response()->json([
                'message' => 'Unauthorized to update this ticket'
            ], 403);
        }

        $validated = $request->validate([
            'subject' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'priority' => 'sometimes|required|string|in:low,medium,high,urgent',
            'status' => 'sometimes|required|string|in:open,in_progress,resolved,closed',
        ]);

        // Only super admins can change status and assign tickets
        if ($user->isSuperAdmin()) {
            if (isset($validated['status']) && $validated['status'] === 'resolved') {
                $validated['resolved_at'] = now();
            }
        } else {
            // Regular users can only update subject, description, and priority
            unset($validated['status']);
        }

        $ticket->update($validated);

        return response()->json([
            'message' => 'Support ticket updated successfully',
            'ticket' => $ticket->fresh()->load(['tenant', 'user', 'assignedUser'])
        ]);
    }

    /**
     * Assign ticket to a support agent (Super admin only).
     */
    public function assign(Request $request, SupportTicket $ticket)
    {
        $user = $request->user();

        if (!$user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Only super admins can assign tickets.'
            ], 403);
        }

        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $ticket->update([
            'assigned_to' => $validated['assigned_to'],
            'status' => 'in_progress'
        ]);

        return response()->json([
            'message' => 'Ticket assigned successfully',
            'ticket' => $ticket->fresh()->load(['tenant', 'user', 'assignedUser'])
        ]);
    }

    /**
     * Close the specified support ticket.
     */
    public function close(Request $request, SupportTicket $ticket)
    {
        $user = $request->user();

        // Check permissions
        if (!$user->isSuperAdmin() && $ticket->tenant_id !== $user->tenant_id) {
            return response()->json([
                'message' => 'Unauthorized to close this ticket'
            ], 403);
        }

        $ticket->update([
            'status' => 'closed',
            'resolved_at' => now()
        ]);

        return response()->json([
            'message' => 'Support ticket closed successfully',
            'ticket' => $ticket->fresh()
        ]);
    }

    /**
     * Get support ticket statistics for current tenant.
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        $query = SupportTicket::query();

        if (!$user->isSuperAdmin()) {
            $query->where('tenant_id', $user->tenant_id);
        }

        $stats = [
            'total' => $query->count(),
            'open' => $query->where('status', 'open')->count(),
            'in_progress' => $query->where('status', 'in_progress')->count(),
            'resolved' => $query->where('status', 'resolved')->count(),
            'closed' => $query->where('status', 'closed')->count(),
        ];

        return response()->json($stats);
    }
}
