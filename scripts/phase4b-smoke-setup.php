<?php

use App\Models\BillableService;
use App\Models\BillableServiceCategory;
use App\Models\ClinicalEncounter;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\RadiologyModality;
use App\Models\RadiologyStudy;
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

$password = getenv('PHASE4B_PASSWORD') ?: 'Phase4BSmoke!';
$doctorEmail = getenv('PHASE4B_DOCTOR_EMAIL') ?: 'phase4b-doctor@example.test';
$radiologyEmail = getenv('PHASE4B_RADIOLOGY_EMAIL') ?: 'phase4b-radiology@example.test';
$approverEmail = getenv('PHASE4B_APPROVER_EMAIL') ?: 'phase4b-approver@example.test';
$unique = time();

$hospital = Hospital::primary() ?? Hospital::query()->firstOrCreate(
    ['legal_name' => 'Phase 4B Smoke Hospital'],
    ['display_name' => 'Phase 4B Smoke Hospital', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'default_currency' => 'NGN'],
);
$facility = Facility::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'code' => 'P4B'],
    ['name' => 'Phase 4B Imaging Clinic', 'facility_type' => 'clinic', 'city' => 'Lagos', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'is_primary' => true, 'status' => 'active'],
);
HospitalSetting::query()->updateOrCreate(['hospital_id' => $hospital->id], ['default_facility_id' => $facility->id, 'currency' => 'NGN']);

foreach ([['radiology_request_number', 'RAD'], ['radiology_accession_number', 'RAC'], ['invoice_number', 'INV']] as [$key, $prefix]) {
    NumberSequence::query()->firstOrCreate(
        ['hospital_id' => $hospital->id, 'facility_id' => null, 'key' => $key],
        ['label' => $key, 'prefix' => $prefix, 'date_format' => 'Y', 'padding_length' => 6, 'next_value' => 1, 'issued_count' => 0, 'status' => 'active'],
    );
}

$doctor = smokeUser($doctorEmail, $password, 'Phase4B', 'Doctor', 'doctor', ['hospital.view', 'patients.view', 'encounters.view', 'radiology.requests.view', 'radiology.requests.order', 'billing.catalogue.view', 'invoices.create', 'invoices.view'], $hospital);
smokeUser($radiologyEmail, $password, 'Phase4B', 'Radiology', 'radiology-staff', ['hospital.view', 'patients.view', 'radiology.catalogue.view', 'radiology.requests.view', 'radiology.schedule.manage', 'radiology.perform', 'radiology.reports.write', 'radiology.reports.verify', 'radiology.attachments.manage'], $hospital);
smokeUser($approverEmail, $password, 'Phase4B', 'Approver', 'radiology-staff', ['hospital.view', 'patients.view', 'radiology.requests.view', 'radiology.reports.approve', 'radiology.reports.amend', 'radiology.attachments.manage'], $hospital);

$category = BillableServiceCategory::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'code' => 'P4B-RAD'],
    ['name' => 'Phase 4B Radiology', 'is_active' => true],
);
$service = BillableService::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'code' => 'P4B-RAD-BILL'],
    ['billable_service_category_id' => $category->id, 'name' => 'Phase 4B billable imaging study', 'is_tax_exempt' => true, 'tax_rate_basis_points' => 0, 'is_discount_eligible' => true, 'is_active' => true],
);
ServicePrice::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'billable_service_id' => $service->id, 'facility_id' => null, 'effective_from' => '2026-01-01'],
    ['currency' => 'NGN', 'amount_minor' => 25000, 'is_active' => true, 'created_by' => $doctor->id, 'reason' => 'Phase 4B smoke price'],
);

$modality = RadiologyModality::query()->updateOrCreate(
    ['hospital_id' => $hospital->id, 'code' => 'P4B-XR'],
    ['facility_id' => $facility->id, 'name' => 'Phase 4B configured X-ray', 'description' => 'Smoke modality configured by radiology staff', 'is_active' => true],
);
RadiologyStudy::query()->updateOrCreate(
    ['hospital_id' => $hospital->id, 'code' => 'P4B-CHEST'],
    ['radiology_modality_id' => $modality->id, 'billable_service_id' => $service->id, 'name' => "Phase 4B imaging study {$unique}", 'description' => 'Smoke imaging study requiring professional configuration', 'preparation_acknowledgements' => ['Configured preparation'], 'safety_screening_acknowledgements' => ['Configured safety acknowledgement'], 'requires_professional_validation' => true, 'is_active' => true],
);

$patient = Patient::query()->create(['hospital_id' => $hospital->id, 'registration_facility_id' => $facility->id, 'registered_by' => $doctor->id, 'hospital_number' => "P4B-{$unique}", 'first_name' => 'Phase4B', 'last_name' => 'Patient', 'date_of_birth' => '1990-01-01', 'sex' => 'female', 'status' => 'active']);
$visit = Visit::query()->create(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'patient_id' => $patient->id, 'clinician_id' => $doctor->staffProfile->id, 'source' => 'walk_in', 'status' => 'in_encounter', 'checked_in_by' => $doctor->id, 'checked_in_at' => now()]);
ClinicalEncounter::query()->create(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'patient_id' => $patient->id, 'visit_id' => $visit->id, 'responsible_clinician_id' => $doctor->staffProfile->id, 'source' => 'walk_in', 'status' => 'in_progress', 'started_by' => $doctor->id, 'started_at' => now()]);

echo "Phase 4B smoke setup ready for {$doctorEmail}, {$radiologyEmail}, {$approverEmail}\n";

function smokeUser(string $email, string $password, string $firstname, string $lastname, string $role, array $permissions, Hospital $hospital): User
{
    $user = User::query()->updateOrCreate(['email' => $email], ['firstname' => $firstname, 'lastname' => $lastname, 'password' => Hash::make($password), 'access_level' => 'admin', 'status' => 'active']);
    $user->forceFill(['email_verified_at' => now()])->save();
    $user->syncRoles([$role]);
    $user->givePermissionTo($permissions);
    StaffProfile::query()->updateOrCreate(['user_id' => $user->id], ['hospital_id' => $hospital->id, 'staff_number' => strtoupper(substr(md5($email), 0, 10)).'-P4B', 'job_title' => $role, 'staff_category' => 'clinical', 'employment_status' => 'active', 'is_active' => true]);

    return $user->load('staffProfile');
}
