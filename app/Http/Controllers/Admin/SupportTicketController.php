<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $query = SupportTicket::with(['tenant:id,name', 'user:id,name', 'assignedUser:id,name'])
            ->when($request->search, function ($query, $search) {
                $query->where('subject', 'like', "%{$search}%")
                    ->orWhere('ticket_number', 'like', "%{$search}%");
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->priority, function ($query, $priority) {
                $query->where('priority', $priority);
            })
            ->when($request->tenant_id, function ($query, $tenantId) {
                $query->where('tenant_id', $tenantId);
            });

        $tickets = $query->latest()->paginate(15);
        $tenants = Tenant::select('id', 'name')->get();

        return Inertia::render('Admin/SupportTickets/Index', [
            'tickets' => $tickets,
            'tenants' => $tenants,
            'filters' => $request->only(['search', 'status', 'priority', 'tenant_id'])
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|in:low,medium,high,urgent',
            'user_id' => 'required|exists:users,id',
        ]);

        $ticket = SupportTicket::create([
            'tenant_id' => $validated['tenant_id'],
            'user_id' => $validated['user_id'],
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'status' => 'open',
        ]);

        return redirect()->route('admin.support-tickets.index')
            ->with('success', 'Support ticket created successfully');
    }

    public function show(SupportTicket $supportTicket)
    {
        $supportTicket->load(['tenant', 'user', 'assignedUser']);

        return Inertia::render('Admin/SupportTickets/Show', [
            'ticket' => $supportTicket
        ]);
    }

    public function update(Request $request, SupportTicket $supportTicket)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|in:low,medium,high,urgent',
            'status' => 'required|in:open,in_progress,closed,resolved',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $supportTicket->update($validated);

        return redirect()->route('admin.support-tickets.index')
            ->with('success', 'Support ticket updated successfully');
    }

    public function assign(Request $request, SupportTicket $supportTicket)
    {
        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $supportTicket->update($validated);

        return back()->with('success', 'Ticket assigned successfully');
    }

    public function destroy(SupportTicket $supportTicket)
    {
        $supportTicket->delete();

        return redirect()->route('admin.support-tickets.index')
            ->with('success', 'Support ticket deleted successfully');
    }
}