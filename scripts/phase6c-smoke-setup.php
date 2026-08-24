<?php

use App\Models\Bed;
use App\Models\BedClass;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryUnit;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\Visit;
use App\Models\Ward;
use App\Models\WardRoom;
use App\Services\AdmissionWorkflowService;
use App\Services\InpatientChartWorkflowService;
use App\Services\InventoryLedgerService;
use App\Services\PrescriptionWorkflowService;
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

$password = getenv('PHASE6C_PASSWORD') ?: 'Phase6CSmoke!';
$email = getenv('PHASE6C_ADMIN_EMAIL') ?: 'phase6c-admin@example.test';
$suffix = (string) now()->timestamp;

$hospital = Hospital::primary() ?? Hospital::query()->firstOrCreate(['legal_name' => 'Phase 6C Smoke Hospital'], ['display_name' => 'Phase 6C Smoke Hospital', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'default_currency' => 'NGN']);
$facility = Facility::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P6C'], ['name' => 'Phase 6C Facility', 'facility_type' => 'hospital', 'city' => 'Lagos', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'is_primary' => true, 'status' => 'active']);
$department = Department::query()->firstOrCreate(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'code' => 'P6C-MED'], ['name' => 'Phase 6C Medicine', 'category' => 'clinical', 'status' => 'active']);
HospitalSetting::query()->updateOrCreate(['hospital_id' => $hospital->id], ['default_facility_id' => $facility->id, 'currency' => 'NGN']);
foreach ([['admission_number', 'Admission', 'ADM'], ['prescription_number', 'Prescription', 'RX']] as [$key, $label, $prefix]) {
    NumberSequence::query()->firstOrCreate(['hospital_id' => $hospital->id, 'facility_id' => null, 'key' => $key], ['label' => $label, 'prefix' => $prefix, 'date_format' => 'Y', 'padding_length' => 6, 'next_value' => 1, 'issued_count' => 0, 'status' => 'active']);
}

$user = User::query()->updateOrCreate(['email' => $email], ['firstname' => 'Phase6C', 'lastname' => 'Nurse', 'password' => Hash::make($password), 'access_level' => 'nurse', 'status' => 'active']);
$user->forceFill(['email_verified_at' => now()])->save();
$user->syncRoles(['nurse']);
$user->givePermissionTo(['hospital.view', 'facilities.view', 'patients.view', 'admissions.view', 'admissions.request', 'admissions.approve', 'admissions.manage', 'inpatient.view', 'inpatient.document', 'prescriptions.view', 'prescriptions.create', 'prescriptions.sign', 'prescriptions.review', 'prescriptions.dispense', 'inventory.stock.receive', 'emar.view', 'emar.administer', 'emar.amend']);
StaffProfile::query()->updateOrCreate(['user_id' => $user->id], ['hospital_id' => $hospital->id, 'staff_number' => 'P6C-'.strtoupper(substr(md5($email), 0, 8)), 'job_title' => 'Ward nurse', 'staff_category' => 'clinical', 'employment_status' => 'active', 'is_active' => true]);
$user->load('staffProfile');

$unit = InventoryUnit::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P6C-TAB'], ['name' => 'Tablet', 'base_factor' => 1, 'is_active' => true]);
$medicine = InventoryItem::query()->firstOrCreate(['hospital_id' => $hospital->id, 'sku' => 'P6C-MED'], ['base_unit_id' => $unit->id, 'type' => 'medicine', 'name' => 'Phase 6C eMAR medicine', 'route' => 'oral', 'is_active' => true]);
$location = InventoryLocation::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P6C-PHARM'], ['facility_id' => $facility->id, 'name' => 'Phase 6C Pharmacy', 'type' => 'pharmacy', 'is_active' => true]);
$batch = InventoryBatch::query()->firstOrCreate(['hospital_id' => $hospital->id, 'inventory_item_id' => $medicine->id, 'batch_number' => 'P6C-'.$suffix], ['inventory_unit_id' => $unit->id, 'expiry_date' => now()->addMonth()->toDateString(), 'state' => 'available', 'unit_cost_minor' => 100, 'currency' => 'NGN']);
app(InventoryLedgerService::class)->receiveBatch(['hospital_id' => $hospital->id, 'inventory_location_id' => $location->id, 'inventory_item_id' => $medicine->id, 'inventory_unit_id' => $unit->id, 'batch_number' => 'P6C-LEDGER-'.$suffix, 'expiry_date' => now()->addMonth()->toDateString(), 'state' => 'available', 'quantity' => 20, 'reason' => 'eMAR smoke stock'], $user);
$batch = InventoryBatch::where('batch_number', 'P6C-LEDGER-'.$suffix)->firstOrFail();

$ward = Ward::query()->firstOrCreate(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'code' => 'P6C-WARD'], ['department_id' => $department->id, 'name' => 'Phase 6C Ward', 'status' => 'active']);
$room = WardRoom::query()->firstOrCreate(['hospital_id' => $hospital->id, 'ward_id' => $ward->id, 'code' => 'P6C-R1'], ['name' => 'Phase 6C Room', 'status' => 'active']);
$bedClass = BedClass::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P6C-GEN'], ['name' => 'Phase 6C General', 'is_active' => true]);
$bed = Bed::query()->create(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'ward_id' => $ward->id, 'ward_room_id' => $room->id, 'bed_class_id' => $bedClass->id, 'code' => 'P6C-'.$suffix, 'label' => 'Phase 6C Bed '.$suffix, 'state' => 'available']);
$patient = Patient::query()->create(['hospital_id' => $hospital->id, 'registration_facility_id' => $facility->id, 'registered_by' => $user->id, 'hospital_number' => 'P6C-'.$suffix, 'status' => 'active', 'first_name' => 'Phase6C', 'last_name' => 'Smoke', 'sex' => 'female']);
$visit = Visit::query()->create(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'department_id' => $department->id, 'patient_id' => $patient->id, 'clinician_id' => $user->staffProfile->id, 'source' => 'walk_in', 'status' => 'in_encounter', 'checked_in_by' => $user->id, 'checked_in_at' => now()]);
$encounter = ClinicalEncounter::query()->create(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'department_id' => $department->id, 'patient_id' => $patient->id, 'visit_id' => $visit->id, 'responsible_clinician_id' => $user->staffProfile->id, 'source' => 'outpatient', 'status' => 'in_progress', 'started_by' => $user->id, 'started_at' => now()]);
$admissions = app(AdmissionWorkflowService::class);
$admission = $admissions->request(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'patient_id' => $patient->id, 'visit_id' => $visit->id, 'clinical_encounter_id' => $encounter->id, 'department_id' => $department->id, 'reason' => 'Phase 6C eMAR admission'], $user);
$admissions->approve($admission, $user);
$admission = $admissions->admit($admission->fresh(), $bed, $user);
$chart = app(InpatientChartWorkflowService::class)->chartForAdmission($admission, $user);

$rxs = app(PrescriptionWorkflowService::class);
$rx = $rxs->createDraft(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'patient_id' => $patient->id, 'clinical_encounter_id' => $encounter->id, 'items' => [
    ['inventory_item_id' => $medicine->id, 'inventory_unit_id' => $unit->id, 'dose' => 'Smoke regular dose', 'route' => 'oral', 'frequency' => 'Smoke regular', 'quantity' => 2, 'medication_order_type' => 'regular', 'scheduled_times' => [now()->format('H:i')], 'start_at' => now()->subHour(), 'end_at' => now()->addDay()],
    ['inventory_item_id' => $medicine->id, 'inventory_unit_id' => $unit->id, 'dose' => 'Smoke STAT dose', 'route' => 'oral', 'frequency' => 'STAT', 'quantity' => 1, 'medication_order_type' => 'stat', 'start_at' => now()],
    ['inventory_item_id' => $medicine->id, 'inventory_unit_id' => $unit->id, 'dose' => 'Smoke PRN dose', 'route' => 'oral', 'frequency' => 'PRN', 'quantity' => 1, 'is_prn' => true, 'medication_order_type' => 'prn', 'prn_instructions' => 'Smoke PRN indication required'],
]], $user);
$rxs->sign($rx, $user);
$rxs->review($rx->fresh(), ['action' => 'approved'], $user);
foreach ($rx->fresh('items')->items as $item) {
    $rxs->dispense($item, $location, $batch, $item->quantity, $user);
}

File::ensureDirectoryExists(storage_path('app/phase6c-smoke'));
File::put(storage_path('app/phase6c-smoke/context.json'), json_encode(['email' => $email, 'password' => $password, 'admission_number' => $admission->admission_number, 'chart_id' => $chart->id], JSON_PRETTY_PRINT));
echo "Phase 6C smoke setup ready for {$email}\n";
