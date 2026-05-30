<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'manage users',
            'manage apartments',
            'manage customers',
            'manage rentals',
            'manage payments',
            'manage expenses',
            'view reports',
            'export reports',
            'view dashboard',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $supervisor = Role::firstOrCreate(['name' => 'supervisor']);
        $tenant = Role::firstOrCreate(['name' => 'tenant']);

        $admin->givePermissionTo(Permission::all());

        $supervisor->givePermissionTo([
            'manage customers',
            'manage rentals',
            'manage payments',
            'view reports',
            'export reports',
            'view dashboard',
        ]);

        $tenant->givePermissionTo([
            'view dashboard',
        ]);
    }
}
