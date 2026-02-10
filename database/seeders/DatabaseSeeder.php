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
            FloorSeeder::class,
            ApartmentSeeder::class,
        ]);

        //Create admin user 
        $admin = User::firstOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'Admin User',
            'password' => bcrypt('12345678'),
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');    
        }

        //Create supervisor user
        $supervisor = User::firstOrCreate([
            'email' => 'sup@example.com',
        ], [
            'name' => 'Supervisor User',
            'password' => bcrypt('12345678'),
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        if (!$supervisor->hasRole('supervisor')) {
            $supervisor->assignRole('supervisor');
        }

        //Create tenant user
        $tenant = User::firstOrCreate([
            'email' => 'tenant@example.com',
        ], [
            'name' => 'Tenant User',
            'password' => bcrypt('12345678'),
            'email_verified_at' => now(),
            'status' => 'active',
        ]);    

        if (!$tenant->hasRole('tenant')) {
            $tenant->assignRole('tenant');    
        }
    }
}
