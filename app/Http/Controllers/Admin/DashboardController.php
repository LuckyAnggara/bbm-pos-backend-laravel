<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_tenants' => Tenant::count(),
            'active_users' => User::count(), // Simplified since we don't have is_active field
            'open_tickets' => SupportTicket::where('status', 'open')->count(),
            'monthly_revenue' => Subscription::where('status', 'active')
                ->whereMonth('created_at', now()->month)
                ->sum('price')
        ];

        $recentTenants = Tenant::with('subscription')
            ->latest()
            ->take(5)
            ->get();

        $recentTickets = SupportTicket::with(['tenant', 'user'])
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'recentTenants' => $recentTenants,
            'recentTickets' => $recentTickets
        ]);
    }
}