<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Branch;
use App\Models\Subscription;

class TenantRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test successful tenant registration.
     */
    public function test_can_register_new_tenant(): void
    {
        $registrationData = [
            'tenant_name' => 'Test Business',
            'contact_email' => 'admin@testbusiness.com',
            'contact_phone' => '+62123456789',
            'address' => 'Test Address',
            'description' => 'Test Description',
            'admin_name' => 'Test Admin',
            'admin_email' => 'admin@example.com',
            'admin_password' => 'password123',
            'branch_name' => 'Main Branch',
        ];

        $response = $this->postJson('/api/tenant/register', $registrationData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'message',
                    'tenant' => [
                        'id',
                        'name',
                        'slug',
                        'contact_email',
                        'status',
                        'trial_ends_at',
                        'subscription'
                    ],
                    'admin' => [
                        'id',
                        'name',
                        'email',
                        'user_type',
                        'is_tenant_owner'
                    ],
                    'branch' => [
                        'id',
                        'name',
                        'tenant_id'
                    ]
                ]);

        // Verify data was created in database
        $this->assertDatabaseHas('tenants', [
            'name' => 'Test Business',
            'contact_email' => 'admin@testbusiness.com',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'user_type' => 'tenant_admin',
            'is_tenant_owner' => true,
        ]);

        $this->assertDatabaseHas('branches', [
            'name' => 'Main Branch',
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'plan_name' => 'trial',
            'status' => 'trial',
        ]);
    }

    /**
     * Test tenant registration with duplicate email fails.
     */
    public function test_cannot_register_with_duplicate_email(): void
    {
        // Create existing tenant
        Tenant::factory()->create([
            'contact_email' => 'admin@testbusiness.com'
        ]);

        $registrationData = [
            'tenant_name' => 'Another Business',
            'contact_email' => 'admin@testbusiness.com', // Duplicate email
            'admin_name' => 'Test Admin',
            'admin_email' => 'admin2@example.com',
            'admin_password' => 'password123',
            'branch_name' => 'Main Branch',
        ];

        $response = $this->postJson('/api/tenant/register', $registrationData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['contact_email']);
    }

    /**
     * Test can access landing page.
     */
    public function test_can_access_landing_page(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'tagline',
                    'features',
                    'pricing_plans',
                    'stats'
                ]);
    }

    /**
     * Test can get subscription plans.
     */
    public function test_can_get_subscription_plans(): void
    {
        $response = $this->get('/api/subscription/plans');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    '*' => [
                        'name',
                        'display_name',
                        'price',
                        'billing_cycle',
                        'max_branches',
                        'max_users',
                        'features'
                    ]
                ]);
    }

    /**
     * Test tenant can login after registration.
     */
    public function test_tenant_admin_can_login_after_registration(): void
    {
        // Register tenant
        $registrationData = [
            'tenant_name' => 'Test Business',
            'contact_email' => 'admin@testbusiness.com',
            'admin_name' => 'Test Admin',
            'admin_email' => 'admin@example.com',
            'admin_password' => 'password123',
            'branch_name' => 'Main Branch',
        ];

        $this->postJson('/api/tenant/register', $registrationData)
             ->assertStatus(201);

        // Try to login
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password123'
        ]);

        $loginResponse->assertStatus(200)
                     ->assertJsonStructure([
                         'token',
                         'user' => [
                             'id',
                             'name',
                             'email',
                             'user_type',
                             'tenant_id'
                         ]
                     ]);
    }

    /**
     * Test tenant admin can access tenant info after login.
     */
    public function test_tenant_admin_can_access_tenant_info(): void
    {
        // Create tenant with admin
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'user_type' => 'tenant_admin',
            'is_tenant_owner' => true,
        ]);
        $subscription = Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'active'
        ]);

        // Login and get token
        $response = $this->actingAs($admin, 'sanctum')
                        ->getJson('/api/tenant/current');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'id',
                    'name',
                    'contact_email',
                    'status',
                    'subscription',
                    'branches',
                    'users'
                ]);
    }
}
