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
        // Dynamic stats from database or cache (in production)
        $stats = [
            'total_businesses' => 500,
            'total_transactions' => 1000000,
            'uptime' => '99.9%',
        ];

        return response()->json([
            'message' => 'Welcome to Mercato POS',
            'tagline' => 'Satu platform, semua cabang terkendali.',
            'features' => [
                'Advanced Point of Sale System',
                'Multi-Branch Management', 
                'Inventory Management',
                'Financial Management',
                'Customer & Supplier Management',
                'Advanced Reporting & Analytics'
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
                'id' => 'paket-startup',
                'name' => 'Paket Startup',
                'subtitle' => 'Ideal untuk bisnis kecil',
                'price' => 199000,
                'price_period' => 'bulan',
                'features' => [
                    '1 Lokasi/Cabang',
                    '2 Kasir/User',
                    'Fitur Kasir',
                    'Laporan Penjualan',
                    'Manajemen Stok',
                    'Support via Chat'
                ],
                'highlighted' => false
            ],
            [
                'id' => 'paket-growth',
                'name' => 'Paket Growth',
                'subtitle' => 'Terpopuler untuk bisnis berkembang',
                'price' => 399000,
                'price_period' => 'bulan',
                'features' => [
                    '3 Lokasi/Cabang',
                    'Unlimited Kasir/User',
                    'Multi-Cabang Sync',
                    'Analisis Penjualan',
                    'Customer Management',
                    'Priority Support'
                ],
                'highlighted' => true
            ],
            [
                'id' => 'paket-pro',
                'name' => 'Paket Pro',
                'subtitle' => 'Untuk bisnis besar dan enterprise',
                'price' => 799000,
                'price_period' => 'bulan',
                'features' => [
                    'Unlimited Lokasi/Cabang',
                    'Unlimited Kasir/User',
                    'Advanced Analytics',
                    'Custom Integrations',
                    'Dedicated Support',
                    'API Access'
                ],
                'highlighted' => false
            ]
        ];
    }
}
