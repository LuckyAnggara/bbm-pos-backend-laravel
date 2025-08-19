<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * Get available subscription plans.
     */
    public function plans()
    {
        $plans = [
            [
                'name' => 'basic',
                'display_name' => 'Basic Plan',
                'price' => 50000,
                'billing_cycle' => 'monthly',
                'max_branches' => 1,
                'max_users' => 3,
                'has_inventory' => true,
                'has_reports' => true,
                'has_employee_management' => false,
                'features' => [
                    'Point of Sale',
                    'Inventory Management',
                    'Basic Reports',
                    'Customer Management'
                ]
            ],
            [
                'name' => 'premium',
                'display_name' => 'Premium Plan',
                'price' => 150000,
                'billing_cycle' => 'monthly',
                'max_branches' => 3,
                'max_users' => 10,
                'has_inventory' => true,
                'has_reports' => true,
                'has_employee_management' => true,
                'features' => [
                    'Everything in Basic',
                    'Multi-branch Support',
                    'Employee Management',
                    'Advanced Reports',
                    'Stock Opname',
                    'Financial Reports'
                ]
            ],
            [
                'name' => 'enterprise',
                'display_name' => 'Enterprise Plan',
                'price' => 500000,
                'billing_cycle' => 'monthly',
                'max_branches' => 0, // unlimited
                'max_users' => 0, // unlimited
                'has_inventory' => true,
                'has_reports' => true,
                'has_employee_management' => true,
                'features' => [
                    'Everything in Premium',
                    'Unlimited Branches',
                    'Unlimited Users',
                    'Custom Reports',
                    'API Access',
                    'Priority Support',
                    'Custom Domain'
                ]
            ]
        ];

        return response()->json($plans);
    }

    /**
     * Get current subscription details.
     */
    public function current(Request $request)
    {
        $user = $request->user();
        $tenant = $user->tenant;

        if (!$tenant) {
            return response()->json([
                'message' => 'Tenant not found'
            ], 404);
        }

        $subscription = $tenant->subscription;

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription found'
            ], 404);
        }

        return response()->json($subscription);
    }

    /**
     * Subscribe to a plan.
     */
    public function subscribe(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isTenantAdmin() && !$user->isTenantOwner()) {
            return response()->json([
                'message' => 'Unauthorized. Only tenant admins can manage subscriptions.'
            ], 403);
        }

        $validated = $request->validate([
            'plan_name' => 'required|string|in:basic,premium,enterprise',
            'billing_cycle' => 'required|string|in:monthly,yearly',
        ]);

        $tenant = $user->tenant;
        
        // Get plan details
        $planDetails = $this->getPlanDetails($validated['plan_name'], $validated['billing_cycle']);
        
        if (!$planDetails) {
            return response()->json([
                'message' => 'Invalid plan'
            ], 400);
        }

        try {
            // End current subscription if exists
            $currentSubscription = $tenant->subscription;
            if ($currentSubscription) {
                $currentSubscription->update(['status' => 'cancelled']);
            }

            // Create new subscription
            $newSubscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_name' => $validated['plan_name'],
                'price' => $planDetails['price'],
                'billing_cycle' => $validated['billing_cycle'],
                'status' => 'active',
                'max_branches' => $planDetails['max_branches'],
                'max_users' => $planDetails['max_users'],
                'has_inventory' => $planDetails['has_inventory'],
                'has_reports' => $planDetails['has_reports'],
                'has_employee_management' => $planDetails['has_employee_management'],
                'starts_at' => now(),
                'ends_at' => $validated['billing_cycle'] === 'yearly' ? now()->addYear() : now()->addMonth(),
                'features' => $planDetails['features'],
            ]);

            return response()->json([
                'message' => 'Subscription updated successfully',
                'subscription' => $newSubscription
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel subscription.
     */
    public function cancel(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isTenantAdmin() && !$user->isTenantOwner()) {
            return response()->json([
                'message' => 'Unauthorized. Only tenant admins can manage subscriptions.'
            ], 403);
        }

        $tenant = $user->tenant;
        $subscription = $tenant->subscription;

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription found'
            ], 404);
        }

        $subscription->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Subscription cancelled successfully'
        ]);
    }

    /**
     * Get plan details.
     */
    private function getPlanDetails(string $planName, string $billingCycle): ?array
    {
        $plans = [
            'basic' => [
                'price' => $billingCycle === 'yearly' ? 500000 : 50000,
                'max_branches' => 1,
                'max_users' => 3,
                'has_inventory' => true,
                'has_reports' => true,
                'has_employee_management' => false,
                'features' => ['Point of Sale', 'Inventory Management', 'Basic Reports', 'Customer Management']
            ],
            'premium' => [
                'price' => $billingCycle === 'yearly' ? 1500000 : 150000,
                'max_branches' => 3,
                'max_users' => 10,
                'has_inventory' => true,
                'has_reports' => true,
                'has_employee_management' => true,
                'features' => ['Everything in Basic', 'Multi-branch Support', 'Employee Management', 'Advanced Reports']
            ],
            'enterprise' => [
                'price' => $billingCycle === 'yearly' ? 5000000 : 500000,
                'max_branches' => 0,
                'max_users' => 0,
                'has_inventory' => true,
                'has_reports' => true,
                'has_employee_management' => true,
                'features' => ['Everything in Premium', 'Unlimited Branches', 'Unlimited Users', 'Custom Reports', 'API Access']
            ]
        ];

        return $plans[$planName] ?? null;
    }
}
