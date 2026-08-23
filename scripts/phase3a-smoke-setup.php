<?php

use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
Artisan::call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);

$email = getenv('PHASE3A_ADMIN_EMAIL') ?: 'phase3a-smoke@example.test';
$password = getenv('PHASE3A_ADMIN_PASSWORD') ?: 'Phase3ASmoke!';
$unique = time();

$hospital = Hospital::primary() ?? Hospital::query()->firstOrCreate(
    ['legal_name' => 'Phase 3A Smoke Hospital'],
    ['display_name' => 'Phase 3A Smoke Hospital', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'default_currency' => 'NGN'],
);

$facility = Facility::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'code' => 'P3A'],
    ['name' => 'Phase 3A Clinic', 'facility_type' => 'clinic', 'city' => 'Lagos', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'is_primary' => true, 'status' => 'active'],
);
HospitalSetting::query()->updateOrCreate(['hospital_id' => $hospital->id], ['default_facility_id' => $facility->id, 'currency' => 'NGN']);
NumberSequence::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'facility_id' => null, 'key' => 'invoice_number'],
    ['label' => 'Invoice number', 'prefix' => 'INV', 'date_format' => 'Y', 'padding_length' => 6, 'next_value' => 1, 'issued_count' => 0, 'status' => 'active'],
);

$user = User::query()->updateOrCreate(
    ['email' => $email],
    ['firstname' => 'Phase3A', 'lastname' => 'Billing', 'password' => Hash::make($password), 'access_level' => 'admin', 'status' => 'active'],
);
$user->forceFill(['email_verified_at' => now()])->save();
$user->syncRoles(['accountant']);
$user->givePermissionTo(['hospital.view', 'facilities.view', 'departments.view', 'patients.view', 'billing.catalogue.view', 'billing.catalogue.manage', 'invoices.view', 'invoices.create', 'invoices.issue', 'invoices.void']);

StaffProfile::query()->updateOrCreate(
    ['user_id' => $user->id],
    ['hospital_id' => $hospital->id, 'staff_number' => 'P3A-BILL', 'job_title' => 'Billing Officer', 'staff_category' => 'administrative', 'employment_status' => 'active', 'is_active' => true],
);

$patient = Patient::query()->create([
    'hospital_id' => $hospital->id,
    'registration_facility_id' => $facility->id,
    'registered_by' => $user->id,
    'hospital_number' => "P3A-{$unique}",
    'first_name' => 'Phase3A',
    'last_name' => 'Patient',
    'date_of_birth' => '1990-01-01',
    'sex' => 'female',
    'status' => 'active',
]);
$patient->phone = '08050000000';
$patient->save();

echo "Phase 3A smoke setup ready for {$email}\n";
