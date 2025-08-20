<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Branch;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantController extends Controller
{
    /**
     * Register a new tenant with admin user.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'tenant_name' => 'required|string|max:255',
            'contact_email' => 'required|email|unique:tenants,contact_email',
            'contact_phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'description' => 'nullable|string',
            // Admin user details
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => 'required|string|min:8',
            // Initial branch details
            'branch_name' => 'required|string|max:255',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                // Create tenant
                $tenant = Tenant::create([
                    'name' => $validated['tenant_name'],
                    'slug' => Str::slug($validated['tenant_name']),
                    'contact_email' => $validated['contact_email'],
                    'contact_phone' => $validated['contact_phone'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'status' => 'active',
                    'trial_ends_at' => now()->addDays(30), // 30-day trial
                ]);

                // Create default branch
                $branch = Branch::create([
                    'tenant_id' => $tenant->id,
                    'name' => $validated['branch_name'],
                    'invoice_name' => $validated['branch_name'],
                    'currency' => 'IDR',
                    'tax_rate' => 11,
                ]);

                // Create admin user
                $adminUser = User::create([
                    'name' => $validated['admin_name'],
                    'email' => $validated['admin_email'],
                    'password' => Hash::make($validated['admin_password']),
                    'role' => 'admin',
                    'user_type' => 'tenant_admin',
                    'is_tenant_owner' => true,
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                ]);

                // Create trial subscription
                Subscription::create([
                    'tenant_id' => $tenant->id,
                    'plan_name' => 'trial',
                    'price' => 0,
                    'billing_cycle' => 'monthly',
                    'status' => 'trial',
                    'max_branches' => 1,
                    'max_users' => 5,
                    'starts_at' => now(),
                    'ends_at' => now()->addDays(30),
                    'trial_ends_at' => now()->addDays(30),
                ]);

                return response()->json([
                    'message' => 'Tenant registered successfully',
                    'tenant' => $tenant->load('subscription'),
                    'admin' => $adminUser->makeHidden(['password']),
                    'branch' => $branch,
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to register tenant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current tenant details.
     */
    public function show(Request $request)
    {
        $user = $request->user();
        
        if ($user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Super admin has no tenant context'
            ], 400);
        }

        $tenant = $user->tenant()->with(['subscription', 'branches', 'users'])->first();
        
        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant not found'
            ], 404);
        }

        return response()->json($tenant);
    }

    /**
     * Update tenant details.
     */
    public function update(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isTenantAdmin() && !$user->isTenantOwner()) {
            return response()->json([
                'message' => 'Unauthorized. Only tenant admins can update tenant details.'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'contact_email' => 'sometimes|required|email',
            'contact_phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'description' => 'nullable|string',
            'logo_url' => 'nullable|url',
        ]);

        $tenant = $user->tenant;
        
        if (isset($validated['contact_email']) && $validated['contact_email'] !== $tenant->contact_email) {
            // Check if email is unique
            $existingTenant = Tenant::where('contact_email', $validated['contact_email'])
                ->where('id', '!=', $tenant->id)
                ->first();
            
            if ($existingTenant) {
                throw ValidationException::withMessages([
                    'contact_email' => ['The contact email has already been taken.']
                ]);
            }
        }

        $tenant->update($validated);

        return response()->json([
            'message' => 'Tenant updated successfully',
            'tenant' => $tenant->fresh()
        ]);
    }

    /**
     * Check tenant name availability.
     */
    public function checkAvailability(Request $request)
    {
        $validated = $request->validate([
            'tenant_name' => 'required|string|max:255',
        ]);

        $name = trim($validated['tenant_name']);
        $slug = Str::slug($name);

        $exists = Tenant::where('slug', $slug)
            ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        return response()->json([
            'available' => !$exists,
            'tenant_name' => $name,
            'slug' => $slug,
            'message' => $exists ? 'Tenant name is already taken.' : 'Tenant name is available.'
        ]);
    }

    /**
     * Get tenant statistics.
     */
    public function stats(Request $request)
    {
        $user = $request->user();
        $tenant = $user->tenant;

        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant not found'
            ], 404);
        }

        $stats = [
            'branches_count' => $tenant->branches()->count(),
            'users_count' => $tenant->users()->count(),
            'subscription_status' => $tenant->subscription?->status ?? 'none',
            'trial_ends_at' => $tenant->trial_ends_at,
            'is_on_trial' => $tenant->isOnTrial(),
            'trial_expired' => $tenant->trialExpired(),
        ];

        return response()->json($stats);
    }
}
