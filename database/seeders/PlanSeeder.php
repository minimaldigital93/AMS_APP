<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // Recommended tiers. Floors are unlimited on every tier (max_floors = null).
        // null on a cap = unlimited. Enterprise is "custom": unlimited caps, no
        // fixed price, and inactive until the superadmin sets a price + activates it.
        $plans = [
            ['slug' => 'starter',  'name' => 'Starter',  'price_usd' => 2.99,  'price_yearly_usd' => 29,  'max_properties' => 1,    'max_rooms' => 10, 'max_staff' => 1,    'max_floors' => null, 'is_active' => true],
            ['slug' => 'basic',    'name' => 'Basic',    'price_usd' => 5.99,  'price_yearly_usd' => 59,  'max_properties' => 2,    'max_rooms' => 20, 'max_staff' => 2,    'max_floors' => null, 'is_active' => true],
            ['slug' => 'growth',   'name' => 'Growth',   'price_usd' => 9.99,  'price_yearly_usd' => 99,  'max_properties' => 5,    'max_rooms' => 40, 'max_staff' => 5,    'max_floors' => null, 'is_active' => true],
            ['slug' => 'business', 'name' => 'Business', 'price_usd' => 16.99, 'price_yearly_usd' => 169, 'max_properties' => 10,   'max_rooms' => 80, 'max_staff' => 10,   'max_floors' => null, 'is_active' => true],
            ['slug' => 'enterprise', 'name' => 'Enterprise', 'price_usd' => 0, 'price_yearly_usd' => null, 'max_properties' => null, 'max_rooms' => null, 'max_staff' => null, 'max_floors' => null, 'is_active' => false],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], array_merge([
                'billing_period_days' => 30,
                'trial_days' => 0,
            ], $plan));
        }

        // Retire legacy tiers without deleting them (existing subscriptions FK them):
        // hide from new signups by deactivating.
        Plan::whereIn('slug', ['pro', 'max'])->update(['is_active' => false]);
    }
}
