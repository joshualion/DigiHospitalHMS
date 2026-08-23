<?php

use App\Models\BillableService;
use App\Models\BillableServiceCategory;
use App\Models\ClinicalEncounter;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\LabReferenceRange;
use App\Models\LabSpecimenType;
use App\Models\LabTest;
use App\Models\LabTestComponent;
use App\Models\LabUnit;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\ServicePrice;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\Visit;
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

$password = getenv('PHASE4A_PASSWORD') ?: 'Phase4ASmoke!';
$doctorEmail = getenv('PHASE4A_DOCTOR_EMAIL') ?: 'phase4a-doctor@example.test';
$labEmail = getenv('PHASE4A_LAB_EMAIL') ?: 'phase4a-lab@example.test';
$approverEmail = getenv('PHASE4A_APPROVER_EMAIL') ?: 'phase4a-approver@example.test';
$unique = time();

$hospital = Hospital::primary() ?? Hospital::query()->firstOrCreate(
    ['legal_name' => 'Phase 4A Smoke Hospital'],
    ['display_name' => 'Phase 4A Smoke Hospital', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'default_currency' => 'NGN'],
);
$facility = Facility::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'code' => 'P4A'],
    ['name' => 'Phase 4A Lab Clinic', 'facility_type' => 'clinic', 'city' => 'Lagos', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'is_primary' => true, 'status' => 'active'],
);
HospitalSetting::query()->updateOrCreate(['hospital_id' => $hospital->id], ['default_facility_id' => $facility->id, 'currency' => 'NGN']);

foreach ([['lab_request_number', 'LAB'], ['lab_accession_number', 'ACC'], ['lab_specimen_number', 'SPC'], ['invoice_number', 'INV']] as [$key, $prefix]) {
    NumberSequence::query()->firstOrCreate(
        ['hospital_id' => $hospital->id, 'facility_id' => null, 'key' => $key],
        ['label' => $key, 'prefix' => $prefix, 'date_format' => 'Y', 'padding_length' => 6, 'next_value' => 1, 'issued_count' => 0, 'status' => 'active'],
    );
}

$doctor = smokeUser($doctorEmail, $password, 'Phase4A', 'Doctor', 'doctor', ['hospital.view', 'patients.view', 'encounters.view', 'lab.requests.view', 'lab.requests.order', 'billing.catalogue.view', 'invoices.create', 'invoices.view'], $hospital);
smokeUser($labEmail, $password, 'Phase4A', 'Lab', 'laboratory-scientist', ['hospital.view', 'patients.view', 'lab.catalogue.view', 'lab.requests.view', 'lab.specimens.manage', 'lab.results.enter', 'lab.results.verify'], $hospital);
smokeUser($approverEmail, $password, 'Phase4A', 'Approver', 'laboratory-scientist', ['hospital.view', 'patients.view', 'lab.requests.view', 'lab.results.approve', 'lab.results.amend'], $hospital);

$category = BillableServiceCategory::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'code' => 'P4A-LAB'],
    ['name' => 'Phase 4A Laboratory', 'is_active' => true],
);
$service = BillableService::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'code' => 'P4A-LAB-BILL'],
    ['billable_service_category_id' => $category->id, 'name' => 'Phase 4A billable lab test', 'is_tax_exempt' => true, 'tax_rate_basis_points' => 0, 'is_discount_eligible' => true, 'is_active' => true],
);
ServicePrice::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'billable_service_id' => $service->id, 'facility_id' => null, 'effective_from' => '2026-01-01'],
    ['currency' => 'NGN', 'amount_minor' => 15000, 'is_active' => true, 'created_by' => $doctor->id, 'reason' => 'Phase 4A smoke price'],
);

$blood = LabSpecimenType::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P4A-BLOOD'], ['name' => 'Phase 4A blood', 'is_active' => true]);
$unit = LabUnit::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P4A-U'], ['name' => 'Phase 4A configured unit', 'is_active' => true]);
$test = LabTest::query()->updateOrCreate(
    ['hospital_id' => $hospital->id, 'code' => 'P4A-LAB'],
    ['default_specimen_type_id' => $blood->id, 'billable_service_id' => $service->id, 'name' => "Phase 4A lab test {$unique}", 'description' => 'Smoke test configured lab test', 'requires_approval' => true, 'is_active' => true],
);
$component = LabTestComponent::query()->updateOrCreate(
    ['lab_test_id' => $test->id, 'code' => 'P4A-HB'],
    ['hospital_id' => $hospital->id, 'lab_unit_id' => $unit->id, 'name' => 'Phase 4A numeric component', 'result_type' => 'numeric', 'sort_order' => 1, 'is_required' => true, 'is_active' => true],
);
LabReferenceRange::query()->firstOrCreate(
    ['lab_test_component_id' => $component->id, 'label' => 'Phase 4A configured range'],
    ['hospital_id' => $hospital->id, 'low_value' => 10, 'high_value' => 20, 'critical_high_value' => 30, 'display_text' => 'Configured smoke range', 'requires_professional_validation' => true, 'is_active' => true],
);

$patient = Patient::query()->create(['hospital_id' => $hospital->id, 'registration_facility_id' => $facility->id, 'registered_by' => $doctor->id, 'hospital_number' => "P4A-{$unique}", 'first_name' => 'Phase4A', 'last_name' => 'Patient', 'date_of_birth' => '1990-01-01', 'sex' => 'female', 'status' => 'active']);
$visit = Visit::query()->create(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'patient_id' => $patient->id, 'clinician_id' => $doctor->staffProfile->id, 'source' => 'walk_in', 'status' => 'in_encounter', 'checked_in_by' => $doctor->id, 'checked_in_at' => now()]);
ClinicalEncounter::query()->create(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'patient_id' => $patient->id, 'visit_id' => $visit->id, 'responsible_clinician_id' => $doctor->staffProfile->id, 'source' => 'walk_in', 'status' => 'in_progress', 'started_by' => $doctor->id, 'started_at' => now()]);

echo "Phase 4A smoke setup ready for {$doctorEmail}, {$labEmail}, {$approverEmail}\n";

function smokeUser(string $email, string $password, string $firstname, string $lastname, string $role, array $permissions, Hospital $hospital): User
{
    $user = User::query()->updateOrCreate(['email' => $email], ['firstname' => $firstname, 'lastname' => $lastname, 'password' => Hash::make($password), 'access_level' => 'admin', 'status' => 'active']);
    $user->forceFill(['email_verified_at' => now()])->save();
    $user->syncRoles([$role]);
    $user->givePermissionTo($permissions);
    StaffProfile::query()->updateOrCreate(['user_id' => $user->id], ['hospital_id' => $hospital->id, 'staff_number' => strtoupper(substr(md5($email), 0, 10)).'-P4A', 'job_title' => $role, 'staff_category' => 'clinical', 'employment_status' => 'active', 'is_active' => true]);

    return $user->load('staffProfile');
}
