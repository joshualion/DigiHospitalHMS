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
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\BloodBankWorkflowService;
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

$password = getenv('PHASE7A_PASSWORD') ?: 'Phase7ASmoke!';
$suffix = (string) now()->timestamp;
$hospital = Hospital::primary() ?? Hospital::query()->firstOrCreate(['legal_name' => 'Phase 7A Smoke Hospital'], ['display_name' => 'Phase 7A Smoke Hospital', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'default_currency' => 'NGN']);
$facility = Facility::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P7A'], ['name' => 'Phase 7A Facility', 'facility_type' => 'hospital', 'city' => 'Lagos', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'is_primary' => true, 'status' => 'active']);
HospitalSetting::query()->updateOrCreate(['hospital_id' => $hospital->id], ['default_facility_id' => $facility->id, 'currency' => 'NGN']);

foreach ([['blood_donor_number', 'Blood donor', 'BDN'], ['blood_donation_number', 'Blood donation', 'DON'], ['blood_collection_number', 'Blood bag', 'BAG'], ['blood_component_number', 'Blood component', 'BCP']] as [$key, $label, $prefix]) {
    NumberSequence::query()->firstOrCreate(['hospital_id' => $hospital->id, 'facility_id' => null, 'key' => $key], ['label' => $label, 'prefix' => $prefix, 'date_format' => 'Y', 'padding_length' => 6, 'next_value' => 1, 'issued_count' => 0, 'status' => 'active']);
}

$permissions = ['hospital.view', 'facilities.view', 'departments.view', 'blood-bank.view', 'blood-bank.catalogue.manage', 'blood-bank.donors.manage', 'blood-bank.screening.manage', 'blood-bank.collections.manage', 'blood-bank.testing.manage', 'blood-bank.testing.verify', 'blood-bank.components.manage', 'blood-bank.components.release', 'blood-bank.amend'];
$users = [];
foreach (['collector' => 'phase7a-collector@example.test', 'verifier' => 'phase7a-verifier@example.test'] as $key => $email) {
    $user = User::query()->updateOrCreate(['email' => $email], ['firstname' => 'Phase7A', 'lastname' => ucfirst($key), 'password' => Hash::make($password), 'access_level' => 'admin', 'status' => 'active']);
    $user->forceFill(['email_verified_at' => now()])->save();
    $user->syncRoles(['blood-bank-staff']);
    $user->givePermissionTo($permissions);
    StaffProfile::query()->updateOrCreate(['user_id' => $user->id], ['hospital_id' => $hospital->id, 'staff_number' => 'P7A-'.strtoupper($key), 'job_title' => 'Blood bank '.$key, 'staff_category' => 'clinical', 'employment_status' => 'active', 'is_active' => true]);
    $users[$key] = $email;
}

$location = BloodBankLocation::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P7A-BB'], ['facility_id' => $facility->id, 'name' => 'Phase 7A Blood Bank', 'type' => 'blood_bank', 'is_active' => true]);
$target = BloodBankLocation::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P7A-SAT'], ['facility_id' => $facility->id, 'name' => 'Phase 7A Satellite Storage', 'type' => 'blood_bank', 'is_active' => true]);
$storage = BloodStorageUnit::query()->firstOrCreate(['hospital_id' => $hospital->id, 'blood_bank_location_id' => $location->id, 'code' => 'P7A-FR1'], ['name' => 'Phase 7A Fridge 1', 'storage_type' => 'refrigerator', 'status' => 'active']);
$category = BloodDonorCategory::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P7A-VOL'], ['name' => 'Phase 7A Voluntary', 'description' => 'Smoke donor category', 'is_active' => true]);
$componentType = BloodComponentType::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P7A-WB'], ['name' => 'Phase 7A Whole Blood', 'default_shelf_life_days' => 35, 'is_active' => true]);
$screeningTest = BloodScreeningTest::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P7A-SCREEN'], ['name' => 'Phase 7A Configured Screening', 'is_required_for_release' => true, 'is_active' => true]);
BloodScreeningTest::where('hospital_id', $hospital->id)->whereKeyNot($screeningTest->id)->update(['is_required_for_release' => false]);

$collector = User::where('email', $users['collector'])->firstOrFail();
$workflow = app(BloodBankWorkflowService::class);
$donor = $workflow->registerDonor([
    'hospital_id' => $hospital->id,
    'blood_donor_category_id' => $category->id,
    'first_name' => 'Smoke',
    'last_name' => 'Donor '.$suffix,
    'phone' => '080'.substr($suffix, -8),
    'consented_at' => now(),
    'consent_reference' => 'CONSENT-'.$suffix,
], $collector);
$workflow->recordScreeningDecision($donor, ['eligibility_status' => 'eligible', 'decision_reason' => 'Manual smoke eligibility decision'], $collector);
$donation = $workflow->collect([
    'hospital_id' => $hospital->id,
    'facility_id' => $facility->id,
    'blood_donor_id' => $donor->id,
    'blood_bank_location_id' => $location->id,
    'bag_type' => 'Smoke configured bag',
    'volume_ml' => 450,
], $collector);
$workflow->enterGroup($donation, ['abo_group' => 'O', 'rh_factor' => 'positive'], $collector);
$workflow->recordScreeningResult($donation, $screeningTest, ['result_value' => 'Manually cleared', 'release_cleared' => true], $collector);
$workflow->prepareComponent($donation, $componentType, $location, $storage, ['volume_ml' => 250, 'expires_on' => today()->addDays(10)], $collector);

File::ensureDirectoryExists(storage_path('app/phase7a-smoke'));
File::put(storage_path('app/phase7a-smoke/context.json'), json_encode(['collector_email' => $users['collector'], 'verifier_email' => $users['verifier'], 'password' => $password, 'suffix' => $suffix, 'donation_url' => '/admin/blood-bank/donations/'.$donation->id, 'target_location' => $target->name], JSON_PRETTY_PRINT));
echo "Phase 7A smoke setup ready.\n";
