<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Subscription;
use App\Models\SupportTicket;
use Illuminate\Http\Request;

class SaasAdminController extends Controller
{
    /**
     * Get SaaS dashboard statistics.
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Only super admins can access SaaS dashboard.'
            ], 403);
        }

        $stats = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('status', 'active')->count(),
            'trial_tenants' => Tenant::whereHas('subscription', function($q) {
                $q->where('status', 'trial');
            })->count(),
            'suspended_tenants' => Tenant::where('status', 'suspended')->count(),
            'total_subscriptions' => Subscription::where('status', 'active')->count(),
            'monthly_revenue' => Subscription::where('status', 'active')
                ->where('billing_cycle', 'monthly')
                ->sum('price'),
            'yearly_revenue' => Subscription::where('status', 'active')
                ->where('billing_cycle', 'yearly')
                ->sum('price'),
            'open_tickets' => SupportTicket::where('status', 'open')->count(),
            'recent_registrations' => Tenant::where('created_at', '>=', now()->subDays(7))->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Get all tenants with pagination.
     */
    public function tenants(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Only super admins can manage tenants.'
            ], 403);
        }

        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');
        $status = $request->get('status');

        $query = Tenant::with(['subscription', 'users' => function($q) {
            $q->where('is_tenant_owner', true);
        }]);

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('contact_email', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $tenants = $query->paginate($perPage);

        return response()->json($tenants);
    }

    /**
     * Update tenant status.
     */
    public function updateTenantStatus(Request $request, Tenant $tenant)
    {
        $user = $request->user();
        
        if (!$user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Only super admins can manage tenants.'
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:active,suspended,cancelled'
        ]);

        $tenant->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Tenant status updated successfully',
            'tenant' => $tenant->fresh()
        ]);
    }

    /**
     * Get subscription analytics.
     */
    public function subscriptionAnalytics(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Only super admins can access analytics.'
            ], 403);
        }

        $planStats = Subscription::where('status', 'active')
            ->selectRaw('plan_name, count(*) as count, sum(price) as revenue')
            ->groupBy('plan_name')
            ->get();

        $billingStats = Subscription::where('status', 'active')
            ->selectRaw('billing_cycle, count(*) as count, sum(price) as revenue')
            ->groupBy('billing_cycle')
            ->get();

        $monthlyGrowth = Subscription::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, count(*) as new_subscriptions')
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'plan_distribution' => $planStats,
            'billing_distribution' => $billingStats,
            'monthly_growth' => $monthlyGrowth
        ]);
    }

    /**
     * Get support ticket overview.
     */
    public function supportOverview(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Only super admins can access support overview.'
            ], 403);
        }

        $ticketStats = [
            'open' => SupportTicket::where('status', 'open')->count(),
            'in_progress' => SupportTicket::where('status', 'in_progress')->count(),
            'resolved' => SupportTicket::where('status', 'resolved')->count(),
            'closed' => SupportTicket::where('status', 'closed')->count(),
        ];

        $priorityStats = SupportTicket::where('status', '!=', 'closed')
            ->selectRaw('priority, count(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        $recentTickets = SupportTicket::with(['tenant', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'status_stats' => $ticketStats,
            'priority_stats' => $priorityStats,
            'recent_tickets' => $recentTickets
        ]);
    }
}
