<?php

use App\Models\Bed;
use App\Models\BedClass;
use App\Models\BillableService;
use App\Models\BillableServiceCategory;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\ServicePrice;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\Visit;
use App\Models\Ward;
use App\Models\WardRoom;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
Artisan::call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);

$password = getenv('PHASE6A_PASSWORD') ?: 'Phase6ASmoke!';
$email = getenv('PHASE6A_ADMIN_EMAIL') ?: 'phase6a-admin@example.test';
$suffix = (string) now()->timestamp;

$hospital = Hospital::primary() ?? Hospital::query()->firstOrCreate(
    ['legal_name' => 'Phase 6A Smoke Hospital'],
    ['display_name' => 'Phase 6A Smoke Hospital', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'default_currency' => 'NGN'],
);
$facility = Facility::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'code' => 'P6A'],
    ['name' => 'Phase 6A Facility', 'facility_type' => 'hospital', 'city' => 'Lagos', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'is_primary' => true, 'status' => 'active'],
);
$department = Department::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'code' => 'P6A-MED'],
    ['name' => 'Phase 6A Medicine', 'category' => 'clinical', 'status' => 'active'],
);
HospitalSetting::query()->updateOrCreate(['hospital_id' => $hospital->id], ['default_facility_id' => $facility->id, 'currency' => 'NGN']);

foreach ([['admission_number', 'Admission number', 'ADM'], ['invoice_number', 'Invoice number', 'INV']] as [$key, $label, $prefix]) {
    NumberSequence::query()->firstOrCreate(['hospital_id' => $hospital->id, 'facility_id' => null, 'key' => $key], ['label' => $label, 'prefix' => $prefix, 'date_format' => 'Y', 'padding_length' => 6, 'next_value' => 1, 'issued_count' => 0, 'status' => 'active']);
}

$user = User::query()->updateOrCreate(['email' => $email], ['firstname' => 'Phase6A', 'lastname' => 'Admin', 'password' => Hash::make($password), 'access_level' => 'admin', 'status' => 'active']);
$user->forceFill(['email_verified_at' => now()])->save();
$user->syncRoles(['doctor']);
$user->givePermissionTo(['hospital.view', 'facilities.view', 'patients.view', 'encounters.view', 'invoices.create', 'admissions.view', 'admissions.request', 'admissions.approve', 'admissions.manage', 'admissions.discharge', 'admissions.discharge.override']);
StaffProfile::query()->updateOrCreate(['user_id' => $user->id], ['hospital_id' => $hospital->id, 'staff_number' => 'P6A-'.strtoupper(substr(md5($email), 0, 8)), 'job_title' => 'Admitting clinician', 'staff_category' => 'clinical', 'employment_status' => 'active', 'is_active' => true]);
$user->load('staffProfile');

$category = BillableServiceCategory::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P6A-ACCOM'], ['name' => 'Phase 6A Accommodation', 'is_active' => true]);
$service = BillableService::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P6A-BED'], ['billable_service_category_id' => $category->id, 'name' => 'Phase 6A bed day', 'is_tax_exempt' => true, 'tax_rate_basis_points' => 0, 'is_discount_eligible' => false, 'is_active' => true]);
ServicePrice::query()->firstOrCreate(['hospital_id' => $hospital->id, 'billable_service_id' => $service->id, 'facility_id' => $facility->id, 'currency' => 'NGN', 'effective_from' => today()], ['amount_minor' => 200000, 'is_active' => true, 'created_by' => $user->id, 'reason' => 'Phase 6A smoke price']);

$bedClass = BedClass::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P6A-GEN'], ['billable_service_id' => $service->id, 'name' => 'Phase 6A General', 'is_active' => true]);
$ward = Ward::query()->firstOrCreate(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'code' => 'P6A-WARD'], ['department_id' => $department->id, 'name' => 'Phase 6A Ward', 'status' => 'active']);
$room = WardRoom::query()->firstOrCreate(['hospital_id' => $hospital->id, 'ward_id' => $ward->id, 'code' => 'P6A-R1'], ['name' => 'Phase 6A Room', 'status' => 'active']);

$bedA = Bed::query()->create(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'ward_id' => $ward->id, 'ward_room_id' => $room->id, 'bed_class_id' => $bedClass->id, 'code' => 'P6A-A-'.$suffix, 'label' => 'Phase 6A Bed A '.$suffix, 'state' => 'available']);
$bedB = Bed::query()->create(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'ward_id' => $ward->id, 'ward_room_id' => $room->id, 'bed_class_id' => $bedClass->id, 'code' => 'P6A-B-'.$suffix, 'label' => 'Phase 6A Bed B '.$suffix, 'state' => 'available']);

$patient = Patient::query()->create(['hospital_id' => $hospital->id, 'registration_facility_id' => $facility->id, 'registered_by' => $user->id, 'hospital_number' => 'P6A-'.$suffix, 'status' => 'active', 'first_name' => 'Phase6A', 'last_name' => 'Smoke', 'sex' => 'female']);
$visit = Visit::query()->create(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'department_id' => $department->id, 'patient_id' => $patient->id, 'clinician_id' => $user->staffProfile->id, 'source' => 'walk_in', 'status' => 'in_encounter', 'checked_in_by' => $user->id, 'checked_in_at' => now()]);
$encounter = ClinicalEncounter::query()->create(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'department_id' => $department->id, 'patient_id' => $patient->id, 'visit_id' => $visit->id, 'responsible_clinician_id' => $user->staffProfile->id, 'source' => 'outpatient', 'status' => 'in_progress', 'started_by' => $user->id, 'started_at' => now()]);

$context = ['email' => $email, 'password' => $password, 'hospital_number' => $patient->hospital_number, 'bed_a' => $bedA->label, 'bed_b' => $bedB->label, 'visit_id' => $visit->id, 'encounter_id' => $encounter->id];
File::ensureDirectoryExists(storage_path('app/phase6a-smoke'));
File::put(storage_path('app/phase6a-smoke/context.json'), json_encode($context, JSON_PRETTY_PRINT));

echo "Phase 6A smoke setup ready for {$email}\n";
