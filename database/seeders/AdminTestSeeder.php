<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\Subscription;
use App\Models\User;
use App\Models\SupportTicket;
use Illuminate\Database\Seeder;

class AdminTestSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Create a super admin user
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@admin.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'user_type' => 'super_admin',
            'is_tenant_owner' => false,
        ]);

        // Create sample tenants
        $tenant1 = Tenant::create([
            'name' => 'Acme Corporation',
            'slug' => 'acme-corp',
            'domain' => 'acme.example.com',
            'contact_email' => 'contact@acme.com',
            'contact_phone' => '+1234567890',
            'address' => '123 Business Street, City, State 12345',
            'status' => 'active',
            'trial_ends_at' => now()->addDays(30),
        ]);

        $tenant2 = Tenant::create([
            'name' => 'Tech Startup Inc',
            'slug' => 'tech-startup',
            'domain' => 'techstartup.example.com',
            'contact_email' => 'hello@techstartup.com',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(15),
        ]);

        $tenant3 = Tenant::create([
            'name' => 'Enterprise Solutions Ltd',
            'slug' => 'enterprise-solutions',
            'contact_email' => 'admin@enterprise.com',
            'status' => 'past_due',
            'trial_ends_at' => now()->subDays(5),
        ]);

        // Create subscriptions for tenants
        Subscription::create([
            'tenant_id' => $tenant1->id,
            'plan_name' => 'pro',
            'price' => 79.99,
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'max_branches' => 5,
            'max_users' => 25,
            'has_inventory' => true,
            'has_reports' => true,
            'has_employee_management' => true,
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->addDays(30),
        ]);

        Subscription::create([
            'tenant_id' => $tenant2->id,
            'plan_name' => 'basic',
            'price' => 29.99,
            'billing_cycle' => 'monthly',
            'status' => 'trial',
            'max_branches' => 1,
            'max_users' => 5,
            'has_inventory' => false,
            'has_reports' => true,
            'has_employee_management' => false,
            'starts_at' => now()->subDays(15),
            'ends_at' => now()->addDays(15),
            'trial_ends_at' => now()->addDays(15),
        ]);

        Subscription::create([
            'tenant_id' => $tenant3->id,
            'plan_name' => 'enterprise',
            'price' => 199.99,
            'billing_cycle' => 'monthly',
            'status' => 'past_due',
            'max_branches' => 999,
            'max_users' => 999,
            'has_inventory' => true,
            'has_reports' => true,
            'has_employee_management' => true,
            'starts_at' => now()->subDays(60),
            'ends_at' => now()->subDays(5),
        ]);

        // Create tenant users
        $tenant1User = User::create([
            'name' => 'John Doe',
            'email' => 'john@acme.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'tenant_id' => $tenant1->id,
            'is_tenant_owner' => true,
        ]);

        $tenant2User = User::create([
            'name' => 'Jane Smith',
            'email' => 'jane@techstartup.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'tenant_id' => $tenant2->id,
            'is_tenant_owner' => true,
        ]);

        User::create([
            'name' => 'Bob Manager',
            'email' => 'bob@acme.com',
            'password' => bcrypt('password'),
            'role' => 'manager',
            'tenant_id' => $tenant1->id,
            'is_tenant_owner' => false,
        ]);

        // Create sample support tickets
        SupportTicket::create([
            'tenant_id' => $tenant1->id,
            'user_id' => $tenant1User->id,
            'subject' => 'Unable to access inventory module',
            'description' => 'I am getting an error when trying to access the inventory management section. The page loads but shows a 500 error.',
            'status' => 'open',
            'priority' => 'high',
        ]);

        SupportTicket::create([
            'tenant_id' => $tenant2->id,
            'user_id' => $tenant2User->id,
            'subject' => 'Feature request: Export reports to PDF',
            'description' => 'Would it be possible to add a feature to export our sales reports to PDF format?',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        SupportTicket::create([
            'tenant_id' => $tenant1->id,
            'user_id' => $tenant1User->id,
            'subject' => 'User permissions not working',
            'description' => 'Some of our cashier users can see admin functions they should not have access to.',
            'status' => 'in_progress',
            'priority' => 'urgent',
            'assigned_to' => $superAdmin->id,
        ]);
    }
}