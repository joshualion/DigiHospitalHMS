<?php

use App\Models\BillableService;
use App\Models\BillableServiceCategory;
use App\Models\ClinicalEncounter;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryUnit;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\PatientAlert;
use App\Models\PatientAllergy;
use App\Models\ServicePrice;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\Visit;
use App\Services\InventoryLedgerService;
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

$password = getenv('PHASE5B_PASSWORD') ?: 'Phase5BSmoke!';
$doctorEmail = getenv('PHASE5B_DOCTOR_EMAIL') ?: 'phase5b-doctor@example.test';
$pharmacistEmail = getenv('PHASE5B_PHARMACIST_EMAIL') ?: 'phase5b-pharmacist@example.test';
$batchNumber = getenv('PHASE5B_BATCH_NUMBER') ?: 'P5B-'.time();

$hospital = Hospital::primary() ?? Hospital::query()->firstOrCreate(
    ['legal_name' => 'Phase 5B Smoke Hospital'],
    ['display_name' => 'Phase 5B Smoke Hospital', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'default_currency' => 'NGN'],
);
$facility = Facility::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'code' => 'P5B'],
    ['name' => 'Phase 5B Pharmacy Facility', 'facility_type' => 'clinic', 'city' => 'Lagos', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'is_primary' => true, 'status' => 'active'],
);
HospitalSetting::query()->updateOrCreate(['hospital_id' => $hospital->id], ['default_facility_id' => $facility->id, 'currency' => 'NGN']);

NumberSequence::query()->firstOrCreate(['hospital_id' => $hospital->id, 'key' => 'prescription_number'], ['prefix' => 'RX', 'next_number' => 1, 'padding' => 6, 'scope' => 'hospital']);
NumberSequence::query()->firstOrCreate(['hospital_id' => $hospital->id, 'key' => 'invoice_number'], ['prefix' => 'INV', 'next_number' => 1, 'padding' => 6, 'scope' => 'hospital']);

$doctor = smokeUser($doctorEmail, $password, 'Phase5B', 'Doctor', 'doctor', ['hospital.view', 'facilities.view', 'patients.view', 'encounters.view', 'prescriptions.view', 'prescriptions.create', 'prescriptions.sign'], $hospital);
$pharmacist = smokeUser($pharmacistEmail, $password, 'Phase5B', 'Pharmacist', 'pharmacist', ['hospital.view', 'facilities.view', 'patients.view', 'inventory.view', 'prescriptions.view', 'prescriptions.review', 'prescriptions.dispense', 'prescriptions.reverse', 'invoices.create'], $hospital);

$category = BillableServiceCategory::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P5B-RX'], ['name' => 'Phase 5B pharmacy billing', 'description' => 'Smoke-test pharmacy billing services.', 'is_active' => true]);
$billable = BillableService::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P5B-MED-BILL'], ['billable_service_category_id' => $category->id, 'name' => 'Phase 5B medicine charge', 'description' => 'Smoke-test configured medicine charge.', 'is_tax_exempt' => true, 'tax_rate_basis_points' => 0, 'is_discount_eligible' => false, 'is_active' => true]);
ServicePrice::query()->updateOrCreate(['hospital_id' => $hospital->id, 'billable_service_id' => $billable->id, 'facility_id' => $facility->id, 'effective_from' => today()->subDay()->toDateString()], ['currency' => 'NGN', 'amount_minor' => 500, 'effective_to' => null, 'is_active' => true, 'created_by' => $pharmacist->id, 'reason' => 'Phase 5B smoke price']);

$unit = InventoryUnit::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P5B-TAB'], ['name' => 'Phase 5B tablet', 'base_factor' => 1, 'requires_pharmacist_validation' => true, 'is_active' => true]);
$item = InventoryItem::query()->updateOrCreate(
    ['hospital_id' => $hospital->id, 'sku' => 'P5B-MED'],
    ['base_unit_id' => $unit->id, 'billable_service_id' => $billable->id, 'type' => 'medicine', 'generic_name' => 'Phase 5B configured generic', 'name' => 'Phase 5B configured medicine', 'dosage_form' => 'Configured form', 'strength' => 'Configured strength', 'route' => 'Configured route', 'description' => 'Smoke medicine requiring pharmacist validation.', 'reorder_level' => 10, 'requires_pharmacist_validation' => true, 'is_active' => true],
);
$location = InventoryLocation::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P5B-PHARM'], ['facility_id' => $facility->id, 'name' => 'Phase 5B Pharmacy', 'type' => 'pharmacy', 'is_active' => true]);

$batch = app(InventoryLedgerService::class)->receiveBatch([
    'hospital_id' => $hospital->id,
    'inventory_item_id' => $item->id,
    'inventory_location_id' => $location->id,
    'inventory_unit_id' => $unit->id,
    'batch_number' => $batchNumber,
    'quantity' => 30,
    'expiry_date' => today()->addYear()->toDateString(),
    'unit_cost_minor' => 100,
    'currency' => 'NGN',
    'state' => 'available',
    'reason' => 'Phase 5B smoke opening stock',
], $pharmacist);

$patient = Patient::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'hospital_number' => 'P5B-000001'],
    ['registration_facility_id' => $facility->id, 'registered_by' => $doctor->id, 'status' => 'active', 'first_name' => 'Phase5B', 'last_name' => 'Patient', 'date_of_birth' => '1990-01-01', 'sex' => 'female', 'address' => 'Smoke address'],
);
PatientAllergy::query()->firstOrCreate(['patient_id' => $patient->id, 'substance' => 'Smoke allergy'], ['hospital_id' => $hospital->id, 'reaction' => 'Rash', 'severity' => 'high', 'status' => 'active', 'recorded_by' => $doctor->id, 'recorded_at' => now()]);
PatientAlert::query()->firstOrCreate(['patient_id' => $patient->id, 'title' => 'Smoke alert'], ['hospital_id' => $hospital->id, 'category' => 'clinical', 'severity' => 'medium', 'status' => 'active', 'recorded_by' => $doctor->id, 'recorded_at' => now()]);

$visit = Visit::query()->firstOrCreate(['hospital_id' => $hospital->id, 'patient_id' => $patient->id, 'source' => 'walk_in', 'status' => 'in_progress'], ['facility_id' => $facility->id, 'clinician_id' => $doctor->staffProfile->id, 'checked_in_by' => $doctor->id, 'checked_in_at' => now()]);
ClinicalEncounter::query()->firstOrCreate(['hospital_id' => $hospital->id, 'visit_id' => $visit->id], ['facility_id' => $facility->id, 'patient_id' => $patient->id, 'responsible_clinician_id' => $doctor->staffProfile->id, 'source' => 'outpatient', 'status' => 'active', 'started_by' => $doctor->id, 'started_at' => now()]);

echo "Phase 5B smoke setup ready for {$doctorEmail}, {$pharmacistEmail}, batch {$batch->batch_number}\n";

function smokeUser(string $email, string $password, string $firstname, string $lastname, string $role, array $permissions, Hospital $hospital): User
{
    $user = User::query()->updateOrCreate(['email' => $email], ['firstname' => $firstname, 'lastname' => $lastname, 'password' => Hash::make($password), 'access_level' => 'admin', 'status' => 'active']);
    $user->forceFill(['email_verified_at' => now()])->save();
    $user->syncRoles([$role]);
    $user->givePermissionTo($permissions);
    StaffProfile::query()->updateOrCreate(['user_id' => $user->id], ['hospital_id' => $hospital->id, 'staff_number' => strtoupper(substr(md5($email), 0, 10)).'-P5B', 'job_title' => $role, 'staff_category' => 'clinical', 'employment_status' => 'active', 'is_active' => true]);

    return $user->load('staffProfile');
}
