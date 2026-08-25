<?php

use App\Models\Admission;
use App\Models\Bed;
use App\Models\BedClass;
use App\Models\BloodBankLocation;
use App\Models\BloodComponentType;
use App\Models\BloodDonorCategory;
use App\Models\BloodScreeningTest;
use App\Models\BloodStorageUnit;
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
use App\Models\Prescription;
use App\Models\PrescriptionDispense;
use App\Models\PrescriptionItem;
use App\Models\PrescriptionReview;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\Visit;
use App\Models\Ward;
use App\Models\WardRoom;
use App\Services\AdmissionWorkflowService;
use App\Services\BloodBankWorkflowService;
use App\Services\BloodRequestWorkflowService;
use App\Services\EmarWorkflowService;
use App\Services\InpatientChartWorkflowService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
Artisan::call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);

$password = getenv('CLEANUP_C_PASSWORD') ?: 'CleanupCSmoke!';
$hospital = Hospital::query()->firstOrCreate(['legal_name' => 'Cleanup C Smoke Hospital'], ['display_name' => 'Cleanup C Smoke Hospital', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'default_currency' => 'NGN']);
$facility = Facility::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNC'], ['name' => 'Cleanup C Facility', 'facility_type' => 'hospital', 'city' => 'Lagos', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'is_primary' => true, 'status' => 'active']);
HospitalSetting::query()->updateOrCreate(['hospital_id' => $hospital->id], ['default_facility_id' => $facility->id, 'currency' => 'NGN', 'operating_preferences' => ['blood_bank' => ['reservation_expiry_minutes' => 30]]]);

foreach ([['admission_number', 'ADM'], ['prescription_number', 'RX'], ['blood_donor_number', 'BDN'], ['blood_donation_number', 'DON'], ['blood_collection_number', 'BAG'], ['blood_component_number', 'BCP'], ['blood_request_number', 'BTR'], ['blood_specimen_label', 'BSP'], ['blood_issue_number', 'BIS']] as [$key, $prefix]) {
    NumberSequence::query()->firstOrCreate(['hospital_id' => $hospital->id, 'facility_id' => null, 'key' => $key], ['label' => $key, 'prefix' => $prefix, 'date_format' => $key === 'blood_specimen_label' ? 'Ymd' : 'Y', 'padding_length' => 4, 'next_value' => 1, 'issued_count' => 0, 'status' => 'active']);
}

$admin = User::query()->updateOrCreate(['email' => 'cleanup-c-admin@example.test'], ['firstname' => 'Cleanup', 'lastname' => 'C Admin', 'password' => Hash::make($password), 'access_level' => 'admin', 'status' => 'active']);
$admin->forceFill(['email_verified_at' => now()])->save();
$admin->syncRoles(['admin']);
$admin->syncPermissions(Permission::query()->pluck('name')->all());
$staff = StaffProfile::query()->updateOrCreate(['user_id' => $admin->id], ['hospital_id' => $hospital->id, 'staff_number' => 'CLNC-ADMIN', 'job_title' => 'Clinician', 'staff_category' => 'clinical', 'employment_status' => 'active', 'is_active' => true]);
$authorizer = User::query()->updateOrCreate(['email' => 'cleanup-c-authorizer@example.test'], ['firstname' => 'Cleanup', 'lastname' => 'C Authorizer', 'password' => Hash::make($password), 'access_level' => 'admin', 'status' => 'active']);
$authorizer->forceFill(['email_verified_at' => now()])->save();
$authorizer->syncRoles(['admin']);
$authorizer->syncPermissions(Permission::query()->pluck('name')->all());
StaffProfile::query()->updateOrCreate(['user_id' => $authorizer->id], ['hospital_id' => $hospital->id, 'staff_number' => 'CLNC-AUTH', 'job_title' => 'Blood Bank Authorizer', 'staff_category' => 'clinical', 'employment_status' => 'active', 'is_active' => true]);

$department = Department::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNC-MED'], ['facility_id' => $facility->id, 'name' => 'Cleanup C Medicine', 'category' => 'clinical', 'status' => 'active']);
$patient = Patient::query()->firstOrCreate(['hospital_id' => $hospital->id, 'hospital_number' => 'CLNC-PAT'], ['registration_facility_id' => $facility->id, 'registered_by' => $admin->id, 'status' => 'active', 'first_name' => 'Cleanup', 'last_name' => 'Inpatient', 'date_of_birth' => '1990-01-01', 'sex' => 'female']);
$visit = Visit::query()->firstOrCreate(['hospital_id' => $hospital->id, 'patient_id' => $patient->id, 'status' => 'in_encounter'], ['facility_id' => $facility->id, 'department_id' => $department->id, 'clinician_id' => $staff->id, 'source' => 'walk_in', 'checked_in_by' => $admin->id, 'checked_in_at' => now()]);
$encounter = ClinicalEncounter::query()->firstOrCreate(['hospital_id' => $hospital->id, 'patient_id' => $patient->id, 'visit_id' => $visit->id], ['facility_id' => $facility->id, 'department_id' => $department->id, 'responsible_clinician_id' => $staff->id, 'source' => 'walk_in', 'status' => 'in_progress', 'started_by' => $admin->id, 'started_at' => now()]);

$admissions = app(AdmissionWorkflowService::class);
$admission = Admission::query()->where('hospital_id', $hospital->id)->where('patient_id', $patient->id)->whereIn('status', ['admitted', 'transferred'])->first();
if (! $admission) {
    $ward = Ward::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNC-WARD'], ['facility_id' => $facility->id, 'department_id' => $department->id, 'name' => 'Cleanup C Ward', 'status' => 'active']);
    $room = WardRoom::query()->firstOrCreate(['hospital_id' => $hospital->id, 'ward_id' => $ward->id, 'code' => 'CLNC-R1'], ['name' => 'Cleanup C Room', 'status' => 'active']);
    $class = BedClass::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNC-GEN'], ['name' => 'Cleanup C General', 'is_active' => true]);
    $bed = Bed::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNC-B1'], ['facility_id' => $facility->id, 'ward_id' => $ward->id, 'ward_room_id' => $room->id, 'bed_class_id' => $class->id, 'label' => 'Cleanup C Bed 1', 'state' => 'available']);
    $admission = $admissions->request(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'patient_id' => $patient->id, 'visit_id' => $visit->id, 'clinical_encounter_id' => $encounter->id, 'department_id' => $department->id, 'reason' => 'Cleanup C inpatient smoke'], $admin);
    $admissions->approve($admission, $admin);
    $admission = $admissions->admit($admission->fresh(), $bed, $admin);
}
$chart = app(InpatientChartWorkflowService::class)->chartForAdmission($admission->fresh(), $admin);
app(InpatientChartWorkflowService::class)->order($chart, ['order_type' => 'monitoring', 'instruction' => 'Cleanup C observations', 'status' => 'active'], $admin);

$unit = InventoryUnit::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNC-EA'], ['name' => 'Each', 'base_factor' => 1, 'is_active' => true]);
$medicine = InventoryItem::query()->firstOrCreate(['hospital_id' => $hospital->id, 'sku' => 'CLNC-MED'], ['base_unit_id' => $unit->id, 'type' => 'medicine', 'name' => 'Cleanup C Medicine', 'route' => 'oral', 'is_active' => true]);
$pharmacy = InventoryLocation::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNC-PHAR'], ['facility_id' => $facility->id, 'name' => 'Cleanup C Pharmacy', 'type' => 'pharmacy', 'is_active' => true]);
$batch = InventoryBatch::query()->firstOrCreate(['hospital_id' => $hospital->id, 'batch_number' => 'CLNC-BATCH'], ['inventory_location_id' => $pharmacy->id, 'inventory_item_id' => $medicine->id, 'inventory_unit_id' => $unit->id, 'expiry_date' => today()->addYear(), 'state' => 'available', 'quantity' => 10]);
$rx = Prescription::query()->firstOrCreate(['hospital_id' => $hospital->id, 'patient_id' => $patient->id, 'prescription_number' => 'CLNC-RX'], ['facility_id' => $facility->id, 'clinical_encounter_id' => $encounter->id, 'prescribing_clinician_id' => $staff->id, 'status' => 'signed', 'created_by' => $admin->id, 'signed_by' => $admin->id, 'signed_at' => now()]);
$rxItem = PrescriptionItem::query()->firstOrCreate(['hospital_id' => $hospital->id, 'prescription_id' => $rx->id, 'medicine_name' => 'Cleanup C Medicine'], ['inventory_item_id' => $medicine->id, 'inventory_unit_id' => $unit->id, 'dose' => '1 tablet', 'route' => 'oral', 'frequency' => 'Daily', 'quantity' => 3, 'dispensed_quantity' => 3, 'medication_order_type' => 'regular', 'scheduled_times' => [now()->format('H:i')], 'start_at' => now()->subHour(), 'end_at' => now()->addDay(), 'status' => 'active']);
PrescriptionReview::query()->firstOrCreate(['hospital_id' => $hospital->id, 'prescription_id' => $rx->id, 'prescription_item_id' => $rxItem->id], ['action' => 'approved', 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
PrescriptionDispense::query()->firstOrCreate(['hospital_id' => $hospital->id, 'prescription_id' => $rx->id, 'prescription_item_id' => $rxItem->id, 'action' => 'dispense'], ['inventory_location_id' => $pharmacy->id, 'inventory_batch_id' => $batch->id, 'quantity' => 3, 'performed_by' => $admin->id, 'performed_at' => now()]);
app(EmarWorkflowService::class)->syncSchedules($chart->fresh(), $admin);

$bbLocation = BloodBankLocation::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNC-BB'], ['facility_id' => $facility->id, 'name' => 'Cleanup C Blood Bank', 'is_active' => true]);
$storage = BloodStorageUnit::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNC-FR'], ['blood_bank_location_id' => $bbLocation->id, 'name' => 'Cleanup C Fridge', 'status' => 'active']);
BloodDonorCategory::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNC-VOL'], ['name' => 'Voluntary', 'is_active' => true]);
$componentType = BloodComponentType::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNC-RBC'], ['name' => 'Cleanup C Red Cells', 'default_shelf_life_days' => 35, 'is_active' => true]);
$screening = BloodScreeningTest::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNC-SCR'], ['name' => 'Cleanup C Screening', 'is_required_for_release' => true, 'is_active' => true]);
$bloodBank = app(BloodBankWorkflowService::class);
$donor = $bloodBank->registerDonor(['hospital_id' => $hospital->id, 'first_name' => 'Cleanup', 'last_name' => 'Donor', 'phone' => '0800CLNC'], $admin);
$bloodBank->recordScreeningDecision($donor, ['eligibility_status' => 'eligible', 'decision_reason' => 'Cleanup C smoke eligibility'], $admin);
$donation = $bloodBank->collect(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'blood_donor_id' => $donor->id, 'blood_bank_location_id' => $bbLocation->id, 'bag_type' => 'Configured bag'], $admin);
$group = $bloodBank->enterGroup($donation, ['abo_group' => 'O', 'rh_factor' => 'positive'], $admin);
$bloodBank->verifyGroup($group->fresh(), $authorizer);
$screeningResult = $bloodBank->recordScreeningResult($donation, $screening, ['result_value' => 'Cleared', 'release_cleared' => true], $admin);
$bloodBank->verifyScreeningResult($screeningResult->fresh(), $authorizer);
$component = $bloodBank->prepareComponent($donation, $componentType, $bbLocation, $storage, ['expires_on' => today()->addDays(10)], $admin);
$bloodBank->releaseComponent($component->fresh(), $authorizer, 'Cleanup C smoke release');
$bloodRequest = app(BloodRequestWorkflowService::class)->create(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'patient_id' => $patient->id, 'clinical_encounter_id' => $encounter->id, 'admission_id' => $admission->id, 'requesting_clinician_id' => $staff->id, 'blood_component_type_id' => $componentType->id, 'quantity_requested' => 1, 'clinical_indication' => 'Cleanup C smoke request', 'priority' => 'routine'], $admin);

File::ensureDirectoryExists(storage_path('app/backend-ui-cleanup-c'));
File::put(storage_path('app/backend-ui-cleanup-c/context.json'), json_encode(['email' => $admin->email, 'password' => $password, 'chart_id' => $chart->id, 'blood_request_id' => $bloodRequest->id, 'donor_id' => $donor->id, 'donation_id' => $donation->id], JSON_PRETTY_PRINT));

echo "Backend UI Cleanup C smoke setup ready.\n";
