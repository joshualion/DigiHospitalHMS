<?php

// PermissionSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'hospital.view',
            'hospital.update',
            'facilities.view',
            'facilities.create',
            'facilities.update',
            'facilities.activate',
            'departments.view',
            'departments.manage',
            'staff.view',
            'staff.invite',
            'staff.update',
            'staff.suspend',
            'staff.assign-facilities',
            'roles.view',
            'roles.assign',
            'permissions.manage',
            'audit.view',
            'audit.export',
            'settings.manage',
            'numbering.manage',
            'website.view',
            'website.edit',
            'website.publish',
            'website.unpublish',
            'website.manage_media',
            'website.manage_navigation',
            'website.manage_seo',
            'website.manage_theme',
            'website.view_revisions',
            'website.restore_revision',
            'patients.view',
            'patients.register',
            'patients.update',
            'patients.archive',
            'patients.record-alerts',
            'patients.view-sensitive',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::where('name', 'superadmin')->first()
            ->givePermissionTo(Permission::all());

        $adminPermissions = [
            'hospital.view',
            'hospital.update',
            'facilities.view',
            'facilities.create',
            'facilities.update',
            'facilities.activate',
            'departments.view',
            'departments.manage',
            'staff.view',
            'staff.invite',
            'staff.update',
            'staff.suspend',
            'staff.assign-facilities',
            'roles.view',
            'roles.assign',
            'audit.view',
            'settings.manage',
            'numbering.manage',
            'website.view',
            'website.edit',
            'website.publish',
            'website.unpublish',
            'website.manage_media',
            'website.manage_navigation',
            'website.manage_seo',
            'website.manage_theme',
            'website.view_revisions',
            'website.restore_revision',
            'patients.view',
            'patients.register',
            'patients.update',
            'patients.archive',
            'patients.record-alerts',
            'patients.view-sensitive',
        ];

        Role::where('name', 'admin')->first()?->syncPermissions(array_values(array_diff($adminPermissions, [
            'website.publish',
            'website.unpublish',
            'website.restore_revision',
        ])));

        Role::where('name', 'hospital-admin')->first()?->syncPermissions($adminPermissions);

        Role::whereIn('name', [
            'pharmacist',
            'laboratory-scientist',
            'radiology-staff',
            'cashier',
            'accountant',
            'storekeeper',
            'blood-bank-staff',
            'hmo-claims-officer',
        ])->get()->each(fn (Role $role) => $role->syncPermissions([
            'hospital.view',
            'facilities.view',
            'departments.view',
        ]));

        Role::where('name', 'receptionist')->first()?->syncPermissions([
            'hospital.view',
            'facilities.view',
            'departments.view',
            'patients.view',
            'patients.register',
            'patients.update',
            'patients.record-alerts',
            'patients.view-sensitive',
        ]);

        Role::whereIn('name', ['doctor', 'nurse'])->get()->each(fn (Role $role) => $role->syncPermissions([
            'hospital.view',
            'facilities.view',
            'departments.view',
            'patients.view',
            'patients.update',
            'patients.record-alerts',
            'patients.view-sensitive',
        ]));

        Role::where('name', 'patient')->first()?->syncPermissions([]);
    }
}
