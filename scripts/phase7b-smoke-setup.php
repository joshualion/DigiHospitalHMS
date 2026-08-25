<?php

use App\Models\BloodBankLocation;
use App\Models\BloodComponentType;
use App\Models\BloodDonorCategory;
use App\Models\BloodScreeningTest;
use App\Models\BloodStorageUnit;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\BloodBankWorkflowService;
use App\Services\BloodRequestWorkflowService;
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

$password = getenv('PHASE7B_PASSWORD') ?: 'Phase7BSmoke!';
$suffix = (string) now()->timestamp;
$hospital = Hospital::primary() ?? Hospital::query()->firstOrCreate(['legal_name' => 'Phase 7B Smoke Hospital'], ['display_name' => 'Phase 7B Smoke Hospital', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'default_currency' => 'NGN']);
$facility = Facility::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P7B'], ['name' => 'Phase 7B Facility', 'facility_type' => 'hospital', 'city' => 'Lagos', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'is_primary' => true, 'status' => 'active']);
HospitalSetting::query()->updateOrCreate(['hospital_id' => $hospital->id], ['default_facility_id' => $facility->id, 'currency' => 'NGN', 'operating_preferences' => ['blood_bank' => ['reservation_expiry_minutes' => 45]]]);

foreach ([['blood_donor_number', 'Blood donor', 'BDN', 'Y'], ['blood_donation_number', 'Blood donation', 'DON', 'Y'], ['blood_collection_number', 'Blood bag', 'BAG', 'Y'], ['blood_component_number', 'Blood component', 'BCP', 'Y'], ['blood_request_number', 'Blood request', 'BTR', 'Y'], ['blood_specimen_label', 'Blood specimen', 'BSP', 'Ymd'], ['blood_issue_number', 'Blood issue', 'BIS', 'Y']] as [$key, $label, $prefix, $dateFormat]) {
    NumberSequence::query()->firstOrCreate(['hospital_id' => $hospital->id, 'facility_id' => null, 'key' => $key], ['label' => $label, 'prefix' => $prefix, 'date_format' => $dateFormat, 'padding_length' => 6, 'next_value' => 1, 'issued_count' => 0, 'status' => 'active']);
}

$permissions = ['hospital.view', 'facilities.view', 'departments.view', 'patients.view', 'blood-bank.view', 'blood-bank.requests.view', 'blood-bank.requests.order', 'blood-bank.requests.manage', 'blood-bank.specimens.manage', 'blood-bank.compatibility.enter', 'blood-bank.compatibility.authorize', 'blood-bank.reservations.manage', 'blood-bank.issues.manage', 'blood-bank.emergency-release.authorize', 'blood-bank.catalogue.manage', 'blood-bank.donors.manage', 'blood-bank.screening.manage', 'blood-bank.collections.manage', 'blood-bank.testing.manage', 'blood-bank.testing.verify', 'blood-bank.components.manage', 'blood-bank.components.release'];
$users = [];
foreach (['tech' => 'phase7b-tech@example.test', 'auth' => 'phase7b-auth@example.test'] as $key => $email) {
    $user = User::query()->updateOrCreate(['email' => $email], ['firstname' => 'Phase7B', 'lastname' => ucfirst($key), 'password' => Hash::make($password), 'access_level' => 'admin', 'status' => 'active']);
    $user->forceFill(['email_verified_at' => now()])->save();
    $user->syncRoles(['blood-bank-staff']);
    $user->givePermissionTo($permissions);
    StaffProfile::query()->updateOrCreate(['user_id' => $user->id], ['hospital_id' => $hospital->id, 'staff_number' => 'P7B-'.strtoupper($key), 'job_title' => 'Blood bank '.$key, 'staff_category' => 'clinical', 'employment_status' => 'active', 'is_active' => true]);
    $users[$key] = $email;
}

$location = BloodBankLocation::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P7B-BB'], ['facility_id' => $facility->id, 'name' => 'Phase 7B Blood Bank', 'type' => 'blood_bank', 'is_active' => true]);
$storage = BloodStorageUnit::query()->firstOrCreate(['hospital_id' => $hospital->id, 'blood_bank_location_id' => $location->id, 'code' => 'P7B-FR1'], ['name' => 'Phase 7B Fridge 1', 'storage_type' => 'refrigerator', 'status' => 'active']);
$category = BloodDonorCategory::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P7B-VOL'], ['name' => 'Phase 7B Voluntary', 'description' => 'Smoke donor category', 'is_active' => true]);
$componentType = BloodComponentType::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P7B-RBC'], ['name' => 'Phase 7B Red Cells', 'default_shelf_life_days' => 35, 'is_active' => true]);
$screeningTest = BloodScreeningTest::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P7B-SCREEN'], ['name' => 'Phase 7B Configured Screening', 'is_required_for_release' => true, 'is_active' => true]);
BloodScreeningTest::where('hospital_id', $hospital->id)->whereKeyNot($screeningTest->id)->update(['is_required_for_release' => false]);

$tech = User::where('email', $users['tech'])->firstOrFail();
$auth = User::where('email', $users['auth'])->firstOrFail();
$patient = Patient::query()->firstOrCreate(['hospital_id' => $hospital->id, 'hospital_number' => 'P7B-PAT-'.$suffix], ['registration_facility_id' => $facility->id, 'registered_by' => $tech->id, 'status' => 'active', 'first_name' => 'Smoke', 'last_name' => 'Recipient '.$suffix, 'sex' => 'female']);
$bank = app(BloodBankWorkflowService::class);

$components = [];
for ($i = 0; $i < 2; $i++) {
    $donor = $bank->registerDonor(['hospital_id' => $hospital->id, 'blood_donor_category_id' => $category->id, 'first_name' => 'Smoke', 'last_name' => 'Donor '.$suffix.' '.$i, 'phone' => '081'.substr($suffix, -7).$i], $tech);
    $bank->recordScreeningDecision($donor, ['eligibility_status' => 'eligible', 'decision_reason' => 'Manual smoke eligibility decision'], $tech);
    $donation = $bank->collect(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'blood_donor_id' => $donor->id, 'blood_bank_location_id' => $location->id, 'bag_type' => 'Smoke bag'], $tech);
    $bank->verifyGroup($bank->enterGroup($donation, ['abo_group' => 'O', 'rh_factor' => 'positive'], $tech)->fresh(), $auth);
    $result = $bank->recordScreeningResult($donation, $screeningTest, ['result_value' => 'Manually cleared', 'release_cleared' => true], $tech);
    $bank->verifyScreeningResult($result->fresh(), $auth);
    $component = $bank->prepareComponent($donation, $componentType, $location, $storage, ['expires_on' => today()->addDays(10)], $tech);
    $components[] = $bank->releaseComponent($component->fresh(), $auth, 'Manual smoke release.');
}

$request = app(BloodRequestWorkflowService::class)->create(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'patient_id' => $patient->id, 'requesting_clinician_id' => $tech->staffProfile->id, 'blood_component_type_id' => $componentType->id, 'quantity_requested' => 2, 'clinical_indication' => 'Smoke workflow indication', 'priority' => 'urgent'], $tech);

File::ensureDirectoryExists(storage_path('app/phase7b-smoke'));
File::put(storage_path('app/phase7b-smoke/context.json'), json_encode(['tech_email' => $users['tech'], 'auth_email' => $users['auth'], 'password' => $password, 'request_id' => $request->id, 'request_url' => '/admin/blood-bank/requests/'.$request->id, 'component_ids' => collect($components)->pluck('id')->all()], JSON_PRETTY_PRINT));
echo "Phase 7B smoke setup ready.\n";
