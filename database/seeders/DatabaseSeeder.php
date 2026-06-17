<?php

namespace Database\Seeders;

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

        // Create superadmin user (SaaS owner / platform operator).
        // The superadmin is never a paying customer, so it gets NO
        // subscription (it bypasses all gating).
        $superadmin = User::firstOrCreate([
            'phone' => '010552223',
        ], [
            'name' => 'Super Admin',
            'password' => bcrypt('12345678'),
            'status' => 'active',
        ]);

        $superadmin->syncRoles(['superadmin']);
        $superadmin->forceFill(['account_id' => $superadmin->id])->save();
    }
}
