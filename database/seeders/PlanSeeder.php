<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['slug' => 'basic', 'name' => 'Basic', 'price_usd' => 12, 'max_floors' => 2, 'max_apartments' => 10],
            ['slug' => 'pro', 'name' => 'Pro', 'price_usd' => 24, 'max_floors' => 4, 'max_apartments' => 200],
            ['slug' => 'max', 'name' => 'Max', 'price_usd' => 50, 'max_floors' => null, 'max_apartments' => null],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
