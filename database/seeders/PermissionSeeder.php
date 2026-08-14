<?php

// PermissionSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'manage pages',
            'manage users',
            'view reports',
            'book appointment',
            'manage appointments',
            'dispense drugs',
            'conduct tests',
            'generate bills',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign permissions to roles
        Role::where('name', 'superadmin')->first()
            ->givePermissionTo(Permission::all());

        Role::where('name', 'admin')->first()
            ?->givePermissionTo(['manage pages', 'manage appointments', 'manage users', 'view reports']);

        Role::where('name', 'doctor')->first()
            ?->givePermissionTo(['book appointment', 'manage appointments', 'view reports']);

        Role::where('name', 'nurse')->first()
            ?->givePermissionTo(['book appointment']);

        Role::where('name', 'receptionist')->first()
            ?->givePermissionTo(['book appointment']);

        Role::where('name', 'pharmacist')->first()
            ?->givePermissionTo(['dispense drugs']);

        Role::where('name', 'laboratorist')->first()
            ?->givePermissionTo(['conduct tests']);

        Role::where('name', 'radiologist')->first()
            ?->givePermissionTo(['conduct tests']); // maybe same as lab

        Role::where('name', 'accountant')->first()
            ?->givePermissionTo(['generate bills', 'view reports']);

        Role::where('name', 'patient')->first()
            ?->givePermissionTo(['book appointment']);
    }
}
