<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Subscription;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    /**
     * Show the landing page.
     */
    public function index()
    {
        // Static stats for demo (in production, these would come from database)
        $stats = [
            'total_businesses' => 150,
            'total_transactions' => 50000,
            'uptime' => '99.9%',
        ];

        return response()->json([
            'message' => 'Welcome to BBM POS SaaS',
            'tagline' => 'Complete Point of Sale Solution for Your Business',
            'features' => [
                'Multi-branch Support',
                'Real-time Inventory Management',
                'Comprehensive Reports',
                'Employee Management',
                'Customer Management',
                'Financial Tracking'
            ],
            'pricing_plans' => $this->getPricingPlans(),
            'stats' => $stats
        ]);
    }

    /**
     * Get pricing plans for landing page.
     */
    public function pricing()
    {
        return response()->json($this->getPricingPlans());
    }

    /**
     * Get features list.
     */
    public function features()
    {
        $features = [
            'pos' => [
                'name' => 'Point of Sale',
                'description' => 'Fast and intuitive checkout process',
                'included_in' => ['basic', 'premium', 'enterprise']
            ],
            'inventory' => [
                'name' => 'Inventory Management',
                'description' => 'Real-time stock tracking and alerts',
                'included_in' => ['basic', 'premium', 'enterprise']
            ],
            'multi_branch' => [
                'name' => 'Multi-branch Support',
                'description' => 'Manage multiple locations from one dashboard',
                'included_in' => ['premium', 'enterprise']
            ],
            'employees' => [
                'name' => 'Employee Management',
                'description' => 'Staff scheduling, payroll, and performance tracking',
                'included_in' => ['premium', 'enterprise']
            ],
            'reports' => [
                'name' => 'Advanced Reports',
                'description' => 'Detailed analytics and business insights',
                'included_in' => ['basic', 'premium', 'enterprise']
            ],
            'api' => [
                'name' => 'API Access',
                'description' => 'Integrate with your existing systems',
                'included_in' => ['enterprise']
            ]
        ];

        return response()->json($features);
    }

    /**
     * Contact form submission.
     */
    public function contact(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        // Here you would typically send an email or save to a contacts table
        // For now, we'll just return a success response

        return response()->json([
            'message' => 'Thank you for your message. We will get back to you soon!'
        ]);
    }

    /**
     * Get detailed pricing plans.
     */
    private function getPricingPlans()
    {
        return [
            [
                'name' => 'basic',
                'display_name' => 'Basic',
                'price' => 50000,
                'price_yearly' => 500000,
                'currency' => 'IDR',
                'description' => 'Perfect for small businesses',
                'max_branches' => 1,
                'max_users' => 3,
                'features' => [
                    'Point of Sale',
                    'Inventory Management',
                    'Basic Reports',
                    'Customer Management',
                    'Email Support'
                ],
                'popular' => false
            ],
            [
                'name' => 'premium',
                'display_name' => 'Premium',
                'price' => 150000,
                'price_yearly' => 1500000,
                'currency' => 'IDR',
                'description' => 'Best for growing businesses',
                'max_branches' => 3,
                'max_users' => 10,
                'features' => [
                    'Everything in Basic',
                    'Multi-branch Support',
                    'Employee Management',
                    'Advanced Reports',
                    'Stock Opname',
                    'Priority Support'
                ],
                'popular' => true
            ],
            [
                'name' => 'enterprise',
                'display_name' => 'Enterprise',
                'price' => 500000,
                'price_yearly' => 5000000,
                'currency' => 'IDR',
                'description' => 'For large organizations',
                'max_branches' => 'Unlimited',
                'max_users' => 'Unlimited',
                'features' => [
                    'Everything in Premium',
                    'Unlimited Branches',
                    'Unlimited Users',
                    'Custom Reports',
                    'API Access',
                    'Custom Domain',
                    'Dedicated Support'
                ],
                'popular' => false
            ]
        ];
    }
}
