<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TenantController extends Controller
{
    public function index(Request $request)
    {
        $query = Tenant::with(['subscription'])
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('domain', 'like', "%{$search}%")
                    ->orWhere('contact_email', 'like', "%{$search}%");
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->plan, function ($query, $plan) {
                $query->whereHas('subscription', function ($q) use ($plan) {
                    $q->where('plan_name', $plan);
                });
            });

        $tenants = $query->latest()->paginate(15);

        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => $tenants,
            'filters' => $request->only(['search', 'status', 'plan'])
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'nullable|string|unique:tenants,domain',
            'contact_email' => 'required|email|unique:tenants,contact_email',
            'plan_name' => 'required|in:basic,pro,enterprise',
            'status' => 'required|in:active,trial,suspended,cancelled,past_due'
        ]);

        $tenant = Tenant::create([
            'name' => $validated['name'],
            'slug' => \Str::slug($validated['name']),
            'domain' => $validated['domain'],
            'contact_email' => $validated['contact_email'],
            'status' => $validated['status'],
            'trial_ends_at' => $validated['status'] === 'trial' ? now()->addDays(30) : null,
        ]);

        // Create subscription
        $planPrices = [
            'basic' => 29.99,
            'pro' => 79.99,
            'enterprise' => 199.99
        ];

        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_name' => $validated['plan_name'],
            'price' => $planPrices[$validated['plan_name']],
            'billing_cycle' => 'monthly',
            'status' => $validated['status'],
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'max_branches' => $validated['plan_name'] === 'basic' ? 1 : ($validated['plan_name'] === 'pro' ? 5 : 999),
            'max_users' => $validated['plan_name'] === 'basic' ? 5 : ($validated['plan_name'] === 'pro' ? 25 : 999),
            'has_inventory' => $validated['plan_name'] !== 'basic',
            'has_reports' => true,
            'has_employee_management' => $validated['plan_name'] !== 'basic',
        ]);

        return redirect()->route('admin.tenants.index')
            ->with('success', 'Tenant created successfully');
    }

    public function show(Tenant $tenant)
    {
        $tenant->load(['subscription', 'users', 'branches', 'supportTickets']);

        return Inertia::render('Admin/Tenants/Show', [
            'tenant' => $tenant
        ]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => ['nullable', 'string', Rule::unique('tenants')->ignore($tenant->id)],
            'contact_email' => ['required', 'email', Rule::unique('tenants')->ignore($tenant->id)],
            'plan_name' => 'required|in:basic,pro,enterprise',
            'status' => 'required|in:active,trial,suspended,cancelled,past_due'
        ]);

        $tenant->update([
            'name' => $validated['name'],
            'slug' => \Str::slug($validated['name']),
            'domain' => $validated['domain'],
            'contact_email' => $validated['contact_email'],
            'status' => $validated['status'],
        ]);

        // Update subscription if plan changed
        if ($tenant->subscription && $tenant->subscription->plan_name !== $validated['plan_name']) {
            $planPrices = [
                'basic' => 29.99,
                'pro' => 79.99,
                'enterprise' => 199.99
            ];

            $tenant->subscription->update([
                'plan_name' => $validated['plan_name'],
                'price' => $planPrices[$validated['plan_name']],
                'status' => $validated['status'],
                'max_branches' => $validated['plan_name'] === 'basic' ? 1 : ($validated['plan_name'] === 'pro' ? 5 : 999),
                'max_users' => $validated['plan_name'] === 'basic' ? 5 : ($validated['plan_name'] === 'pro' ? 25 : 999),
                'has_inventory' => $validated['plan_name'] !== 'basic',
                'has_employee_management' => $validated['plan_name'] !== 'basic',
            ]);
        }

        return redirect()->route('admin.tenants.index')
            ->with('success', 'Tenant updated successfully');
    }

    public function destroy(Tenant $tenant)
    {
        $tenant->delete();

        return redirect()->route('admin.tenants.index')
            ->with('success', 'Tenant deleted successfully');
    }
}