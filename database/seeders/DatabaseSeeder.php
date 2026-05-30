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
        ]);

        // Create admin user
        $admin = User::firstOrCreate([
            'phone' => '017552223',
        ], [
            'name' => 'Admin User',
            'password' => bcrypt('12345678'),
            'status' => 'active',
        ]);

        $admin->syncRoles(['admin']);

        // Create supervisor user
        $supervisor = User::firstOrCreate([
            'phone' => '017552224',
        ], [
            'name' => 'Supervisor User',
            'password' => bcrypt('12345678'),
            'status' => 'active',
        ]);

        $supervisor->syncRoles(['supervisor']);

        // Create tenant user
        $tenant = User::firstOrCreate([
            'phone' => '017552225',
        ], [
            'name' => 'Tenant User',
            'password' => bcrypt('12345678'),
            'status' => 'active',
        ]);

        $tenant->syncRoles(['tenant']);
    }
}
