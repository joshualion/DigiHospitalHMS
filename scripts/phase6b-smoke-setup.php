<?php

use App\Models\Bed;
use App\Models\BedClass;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\Visit;
use App\Models\Ward;
use App\Models\WardRoom;
use App\Services\AdmissionWorkflowService;
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

$password = getenv('PHASE6B_PASSWORD') ?: 'Phase6BSmoke!';
$email = getenv('PHASE6B_ADMIN_EMAIL') ?: 'phase6b-admin@example.test';
$suffix = (string) now()->timestamp;

$hospital = Hospital::primary() ?? Hospital::query()->firstOrCreate(
    ['legal_name' => 'Phase 6B Smoke Hospital'],
    ['display_name' => 'Phase 6B Smoke Hospital', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'default_currency' => 'NGN'],
);
$facility = Facility::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P6B'], ['name' => 'Phase 6B Facility', 'facility_type' => 'hospital', 'city' => 'Lagos', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'is_primary' => true, 'status' => 'active']);
$department = Department::query()->firstOrCreate(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'code' => 'P6B-MED'], ['name' => 'Phase 6B Medicine', 'category' => 'clinical', 'status' => 'active']);
HospitalSetting::query()->updateOrCreate(['hospital_id' => $hospital->id], ['default_facility_id' => $facility->id, 'currency' => 'NGN']);
NumberSequence::query()->firstOrCreate(['hospital_id' => $hospital->id, 'facility_id' => null, 'key' => 'admission_number'], ['label' => 'Admission number', 'prefix' => 'ADM', 'date_format' => 'Y', 'padding_length' => 6, 'next_value' => 1, 'issued_count' => 0, 'status' => 'active']);

$user = User::query()->updateOrCreate(['email' => $email], ['firstname' => 'Phase6B', 'lastname' => 'Clinician', 'password' => Hash::make($password), 'access_level' => 'doctor', 'status' => 'active']);
$user->forceFill(['email_verified_at' => now()])->save();
$user->syncRoles(['doctor']);
$user->givePermissionTo(['hospital.view', 'facilities.view', 'patients.view', 'admissions.view', 'admissions.request', 'admissions.approve', 'admissions.manage', 'inpatient.view', 'inpatient.document', 'inpatient.sign', 'inpatient.orders', 'inpatient.handover', 'inpatient.discharge-summary.sign']);
StaffProfile::query()->updateOrCreate(['user_id' => $user->id], ['hospital_id' => $hospital->id, 'staff_number' => 'P6B-'.strtoupper(substr(md5($email), 0, 8)), 'job_title' => 'Ward clinician', 'staff_category' => 'clinical', 'employment_status' => 'active', 'is_active' => true]);
$user->load('staffProfile');

$ward = Ward::query()->firstOrCreate(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'code' => 'P6B-WARD'], ['department_id' => $department->id, 'name' => 'Phase 6B Ward', 'status' => 'active']);
$room = WardRoom::query()->firstOrCreate(['hospital_id' => $hospital->id, 'ward_id' => $ward->id, 'code' => 'P6B-R1'], ['name' => 'Phase 6B Room', 'status' => 'active']);
$bedClass = BedClass::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P6B-GEN'], ['name' => 'Phase 6B General', 'is_active' => true]);
$bed = Bed::query()->create(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'ward_id' => $ward->id, 'ward_room_id' => $room->id, 'bed_class_id' => $bedClass->id, 'code' => 'P6B-'.$suffix, 'label' => 'Phase 6B Bed '.$suffix, 'state' => 'available']);

$patient = Patient::query()->create(['hospital_id' => $hospital->id, 'registration_facility_id' => $facility->id, 'registered_by' => $user->id, 'hospital_number' => 'P6B-'.$suffix, 'status' => 'active', 'first_name' => 'Phase6B', 'last_name' => 'Smoke', 'sex' => 'female']);
$visit = Visit::query()->create(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'department_id' => $department->id, 'patient_id' => $patient->id, 'clinician_id' => $user->staffProfile->id, 'source' => 'walk_in', 'status' => 'in_encounter', 'checked_in_by' => $user->id, 'checked_in_at' => now()]);
$encounter = ClinicalEncounter::query()->create(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'department_id' => $department->id, 'patient_id' => $patient->id, 'visit_id' => $visit->id, 'responsible_clinician_id' => $user->staffProfile->id, 'source' => 'outpatient', 'status' => 'in_progress', 'started_by' => $user->id, 'started_at' => now()]);

$admissions = app(AdmissionWorkflowService::class);
$admission = $admissions->request(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'patient_id' => $patient->id, 'visit_id' => $visit->id, 'clinical_encounter_id' => $encounter->id, 'department_id' => $department->id, 'reason' => 'Phase 6B smoke admission', 'provisional_diagnosis' => 'Configured inpatient problem'], $user);
$admissions->approve($admission, $user);
$admission = $admissions->admit($admission->fresh(), $bed, $user);

$context = ['email' => $email, 'password' => $password, 'hospital_number' => $patient->hospital_number, 'admission_number' => $admission->admission_number];
File::ensureDirectoryExists(storage_path('app/phase6b-smoke'));
File::put(storage_path('app/phase6b-smoke/context.json'), json_encode($context, JSON_PRETTY_PRINT));

echo "Phase 6B smoke setup ready for {$email}\n";
