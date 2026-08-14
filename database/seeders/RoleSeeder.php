<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'superadmin',
            'admin',
            'hospital-admin',
            'receptionist',
            'doctor',
            'nurse',
            'pharmacist',
            'laboratory-scientist',
            'radiology-staff',
            'cashier',
            'accountant',
            'storekeeper',
            'blood-bank-staff',
            'hmo-claims-officer',
            'patient',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
