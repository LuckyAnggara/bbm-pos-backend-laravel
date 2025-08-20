<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plans = ['basic', 'premium', 'enterprise'];
        $plan = fake()->randomElement($plans);
        
        $prices = [
            'basic' => 50000,
            'premium' => 150000,
            'enterprise' => 500000,
        ];
        
        return [
            'tenant_id' => \App\Models\Tenant::factory(),
            'plan_name' => $plan,
            'price' => $prices[$plan],
            'billing_cycle' => fake()->randomElement(['monthly', 'yearly']),
            'status' => 'active',
            'max_branches' => $plan === 'basic' ? 1 : ($plan === 'premium' ? 3 : 0),
            'max_users' => $plan === 'basic' ? 3 : ($plan === 'premium' ? 10 : 0),
            'has_inventory' => true,
            'has_reports' => true,
            'has_employee_management' => $plan !== 'basic',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'features' => ['POS System', 'Inventory Management'],
        ];
    }
}
