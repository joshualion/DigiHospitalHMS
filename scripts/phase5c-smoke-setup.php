<?php

use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryUnit;
use App\Models\NumberSequence;
use App\Models\ProcurementApprovalLimit;
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

$password = getenv('PHASE5C_PASSWORD') ?: 'Phase5CSmoke!';
$storekeeperEmail = getenv('PHASE5C_STOREKEEPER_EMAIL') ?: 'phase5c-storekeeper@example.test';
$approverEmail = getenv('PHASE5C_APPROVER_EMAIL') ?: 'phase5c-approver@example.test';

$hospital = Hospital::primary() ?? Hospital::query()->firstOrCreate(
    ['legal_name' => 'Phase 5C Smoke Hospital'],
    ['display_name' => 'Phase 5C Smoke Hospital', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'default_currency' => 'NGN'],
);
$facility = Facility::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'code' => 'P5C'],
    ['name' => 'Phase 5C Facility', 'facility_type' => 'clinic', 'city' => 'Lagos', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'is_primary' => true, 'status' => 'active'],
);
HospitalSetting::query()->updateOrCreate(['hospital_id' => $hospital->id], ['default_facility_id' => $facility->id, 'currency' => 'NGN']);

foreach ([['purchase_order_number', 'Purchase order', 'PO'], ['goods_receipt_number', 'Goods receipt', 'GRN']] as [$key, $label, $prefix]) {
    NumberSequence::query()->firstOrCreate(['hospital_id' => $hospital->id, 'facility_id' => null, 'key' => $key], ['label' => $label, 'prefix' => $prefix, 'date_format' => 'Y', 'padding_length' => 6, 'next_value' => 1, 'issued_count' => 0, 'status' => 'active']);
}

$storekeeper = smokeUser($storekeeperEmail, $password, 'Phase5C', 'Storekeeper', 'storekeeper', ['hospital.view', 'facilities.view', 'inventory.view', 'procurement.view', 'procurement.suppliers.manage', 'procurement.requisitions.create', 'procurement.receive'], $hospital);
smokeUser($approverEmail, $password, 'Phase5C', 'Approver', 'pharmacist', ['hospital.view', 'facilities.view', 'inventory.view', 'procurement.view', 'procurement.requisitions.approve', 'procurement.receive', 'procurement.reverse', 'procurement.over-receive'], $hospital);

ProcurementApprovalLimit::query()->updateOrCreate(['hospital_id' => $hospital->id, 'role_name' => 'pharmacist', 'currency' => 'NGN'], ['limit_minor' => 100000000, 'is_active' => true]);

$unit = InventoryUnit::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P5C-EACH'], ['name' => 'Phase 5C each', 'base_factor' => 1, 'is_active' => true]);
InventoryLocation::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'P5C-MAIN'], ['facility_id' => $facility->id, 'name' => 'Phase 5C Main Store', 'type' => 'main_store', 'is_active' => true]);
InventoryItem::query()->updateOrCreate(['hospital_id' => $hospital->id, 'sku' => 'P5C-MED'], ['base_unit_id' => $unit->id, 'type' => 'medicine', 'name' => 'Phase 5C configured medicine', 'generic_name' => 'Phase 5C generic', 'reorder_level' => 25, 'requires_pharmacist_validation' => true, 'is_active' => true]);

echo "Phase 5C smoke setup ready for {$storekeeperEmail}, {$approverEmail}\n";

function smokeUser(string $email, string $password, string $firstname, string $lastname, string $role, array $permissions, Hospital $hospital): User
{
    $user = User::query()->updateOrCreate(['email' => $email], ['firstname' => $firstname, 'lastname' => $lastname, 'password' => Hash::make($password), 'access_level' => 'admin', 'status' => 'active']);
    $user->forceFill(['email_verified_at' => now()])->save();
    $user->syncRoles([$role]);
    $user->givePermissionTo($permissions);
    StaffProfile::query()->updateOrCreate(['user_id' => $user->id], ['hospital_id' => $hospital->id, 'staff_number' => strtoupper(substr(md5($email), 0, 10)).'-P5C', 'job_title' => $role, 'staff_category' => 'operations', 'employment_status' => 'active', 'is_active' => true]);

    return $user->load('staffProfile');
}
