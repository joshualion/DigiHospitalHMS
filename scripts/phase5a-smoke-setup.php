<?php

use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryUnit;
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

$password = getenv('PHASE5A_PASSWORD') ?: 'Phase5ASmoke!';
$storekeeperEmail = getenv('PHASE5A_STOREKEEPER_EMAIL') ?: 'phase5a-storekeeper@example.test';
$pharmacistEmail = getenv('PHASE5A_PHARMACIST_EMAIL') ?: 'phase5a-pharmacist@example.test';
$unique = time();

$hospital = Hospital::primary() ?? Hospital::query()->firstOrCreate(
    ['legal_name' => 'Phase 5A Smoke Hospital'],
    ['display_name' => 'Phase 5A Smoke Hospital', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'default_currency' => 'NGN'],
);
$facility = Facility::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'code' => 'P5A'],
    ['name' => 'Phase 5A Pharmacy Facility', 'facility_type' => 'clinic', 'city' => 'Lagos', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'is_primary' => true, 'status' => 'active'],
);
HospitalSetting::query()->updateOrCreate(['hospital_id' => $hospital->id], ['default_facility_id' => $facility->id, 'currency' => 'NGN']);

smokeUser($storekeeperEmail, $password, 'Phase5A', 'Storekeeper', 'storekeeper', ['hospital.view', 'facilities.view', 'inventory.view', 'inventory.catalogue.manage', 'inventory.stock.receive', 'inventory.stock.transfer', 'inventory.stock.adjust'], $hospital);
smokeUser($pharmacistEmail, $password, 'Phase5A', 'Pharmacist', 'pharmacist', ['hospital.view', 'facilities.view', 'inventory.view', 'inventory.catalogue.manage', 'inventory.stock.receive', 'inventory.stock.transfer', 'inventory.stock.adjust', 'inventory.adjustments.approve'], $hospital);

$each = InventoryUnit::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P5A-EACH'], ['name' => 'Phase 5A each', 'base_factor' => 1, 'requires_pharmacist_validation' => true, 'is_active' => true]);
InventoryUnit::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P5A-PACK'], ['name' => 'Phase 5A pack of 10', 'base_unit_id' => $each->id, 'base_factor' => 10, 'requires_pharmacist_validation' => true, 'is_active' => true]);

InventoryLocation::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P5A-MAIN'], ['facility_id' => $facility->id, 'name' => 'Phase 5A Main Store', 'type' => 'main_store', 'is_active' => true]);
InventoryLocation::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P5A-PHARM'], ['facility_id' => $facility->id, 'name' => 'Phase 5A Pharmacy', 'type' => 'pharmacy', 'is_active' => true]);

InventoryItem::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'sku' => 'P5A-MED'],
    ['base_unit_id' => $each->id, 'type' => 'medicine', 'generic_name' => 'Phase 5A configured generic', 'name' => "Phase 5A configured medicine {$unique}", 'dosage_form' => 'Configured form', 'strength' => 'Configured strength', 'route' => 'Configured route', 'description' => 'Smoke medicine requiring pharmacist validation.', 'reorder_level' => 15, 'requires_pharmacist_validation' => true, 'is_active' => true],
);

echo "Phase 5A smoke setup ready for {$storekeeperEmail}, {$pharmacistEmail}\n";

function smokeUser(string $email, string $password, string $firstname, string $lastname, string $role, array $permissions, Hospital $hospital): User
{
    $user = User::query()->updateOrCreate(['email' => $email], ['firstname' => $firstname, 'lastname' => $lastname, 'password' => Hash::make($password), 'access_level' => 'admin', 'status' => 'active']);
    $user->forceFill(['email_verified_at' => now()])->save();
    $user->syncRoles([$role]);
    $user->givePermissionTo($permissions);
    StaffProfile::query()->updateOrCreate(['user_id' => $user->id], ['hospital_id' => $hospital->id, 'staff_number' => strtoupper(substr(md5($email), 0, 10)).'-P5A', 'job_title' => $role, 'staff_category' => 'operations', 'employment_status' => 'active', 'is_active' => true]);

    return $user->load('staffProfile');
}
