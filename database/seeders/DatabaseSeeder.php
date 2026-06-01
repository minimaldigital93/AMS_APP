<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            PlanSeeder::class,
        ]);

        // Create superadmin user (SaaS owner / platform operator)
        $superadmin = User::firstOrCreate([
            'phone' => '017552222',
        ], [
            'name' => 'Super Admin',
            'password' => bcrypt('12345678'),
            'status' => 'active',
        ]);

        $superadmin->syncRoles(['superadmin']);
        $superadmin->forceFill(['account_id' => $superadmin->id])->save();

        // Create admin user
        $admin = User::firstOrCreate([
            'phone' => '017552223',
        ], [
            'name' => 'Admin User',
            'password' => bcrypt('12345678'),
            'status' => 'active',
        ]);

        $admin->syncRoles(['admin']);
        $admin->forceFill(['account_id' => $admin->id])->save();

        // Create supervisor user
        $supervisor = User::firstOrCreate([
            'phone' => '017552224',
        ], [
            'name' => 'Supervisor User',
            'password' => bcrypt('12345678'),
            'status' => 'active',
        ]);

        $supervisor->syncRoles(['supervisor']);
        $supervisor->forceFill(['account_id' => $admin->id])->save();

        // Create tenant user
        $tenant = User::firstOrCreate([
            'phone' => '017552225',
        ], [
            'name' => 'Tenant User',
            'password' => bcrypt('12345678'),
            'status' => 'active',
        ]);

        $tenant->syncRoles(['tenant']);
        $tenant->forceFill(['account_id' => $admin->id])->save();

        // Give the demo ADMIN an active subscription so plan limits are live.
        // The superadmin is the platform operator — it is never a paying
        // customer, so it gets NO subscription (it bypasses all gating) and is
        // therefore never counted in MRR / active-subscription totals.
        $this->activateSubscription($admin->id, 'pro');
    }

    private function activateSubscription(int $accountId, string $planSlug): void
    {
        $plan = Plan::where('slug', $planSlug)->first();
        if (! $plan) {
            return;
        }

        Subscription::updateOrCreate(
            ['account_id' => $accountId],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'started_at' => now(),
                'expires_at' => now()->addDays($plan->billing_period_days),
            ]
        );
    }
}
