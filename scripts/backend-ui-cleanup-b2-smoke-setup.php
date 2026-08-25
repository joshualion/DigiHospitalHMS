<?php

use App\Models\BillableService;
use App\Models\BillableServiceCategory;
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
use App\Models\ProcurementApprovalLimit;
use App\Models\PurchaseRequisition;
use App\Models\StaffProfile;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Visit;
use App\Services\InventoryLedgerService;
use App\Services\PrescriptionWorkflowService;
use App\Services\ProcurementWorkflowService;
use App\Services\ServicePricingService;
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

$password = getenv('CLEANUP_B2_PASSWORD') ?: 'CleanupB2Smoke!';
$suffix = 'CLNB2';
$hospital = Hospital::query()->firstOrCreate(['legal_name' => 'Cleanup B2 Smoke Hospital'], ['display_name' => 'Cleanup B2 Smoke Hospital', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'default_currency' => 'NGN']);
$facility = Facility::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNB2'], ['name' => 'Cleanup B2 Facility', 'facility_type' => 'hospital', 'city' => 'Lagos', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'is_primary' => true, 'status' => 'active']);
HospitalSetting::query()->updateOrCreate(['hospital_id' => $hospital->id], ['default_facility_id' => $facility->id, 'currency' => 'NGN']);

foreach ([['prescription_number', 'Prescription', 'RX'], ['invoice_number', 'Invoice', 'INV'], ['purchase_order_number', 'PO', 'PO'], ['goods_receipt_number', 'GRN', 'GRN']] as [$key, $label, $prefix]) {
    NumberSequence::query()->firstOrCreate(['hospital_id' => $hospital->id, 'facility_id' => null, 'key' => $key], ['label' => $label, 'prefix' => $prefix, 'date_format' => 'Y', 'padding_length' => 4, 'next_value' => 1, 'issued_count' => 0, 'status' => 'active']);
}

$admin = User::query()->updateOrCreate(['email' => 'cleanup-b2-admin@example.test'], ['firstname' => 'Cleanup', 'lastname' => 'B2 Admin', 'password' => Hash::make($password), 'access_level' => 'admin', 'status' => 'active']);
$admin->forceFill(['email_verified_at' => now()])->save();
$admin->syncRoles(['admin']);
$admin->syncPermissions(Permission::query()->pluck('name')->all());
$staff = StaffProfile::query()->updateOrCreate(['user_id' => $admin->id], ['hospital_id' => $hospital->id, 'staff_number' => 'CLNB2-ADMIN', 'job_title' => 'Pharmacist', 'staff_category' => 'clinical', 'employment_status' => 'active', 'is_active' => true]);
$approver = User::query()->updateOrCreate(['email' => 'cleanup-b2-approver@example.test'], ['firstname' => 'Cleanup', 'lastname' => 'Approver', 'password' => Hash::make($password), 'access_level' => 'admin', 'status' => 'active']);
$approver->forceFill(['email_verified_at' => now()])->save();
$approver->syncRoles(['admin']);
$approver->syncPermissions(Permission::query()->pluck('name')->all());
StaffProfile::query()->updateOrCreate(['user_id' => $approver->id], ['hospital_id' => $hospital->id, 'staff_number' => 'CLNB2-APPROVER', 'job_title' => 'Approver', 'staff_category' => 'operations', 'employment_status' => 'active', 'is_active' => true]);

$department = Department::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNB2-PHARM'], ['facility_id' => $facility->id, 'name' => 'Cleanup B2 Pharmacy', 'category' => 'clinical', 'status' => 'active']);
$patient = Patient::query()->firstOrCreate(['hospital_id' => $hospital->id, 'hospital_number' => 'CLNB2-PAT'], ['registration_facility_id' => $facility->id, 'registered_by' => $admin->id, 'status' => 'active', 'first_name' => 'Cleanup', 'last_name' => 'Pharmacy', 'date_of_birth' => '1990-01-01', 'sex' => 'female']);
$visit = Visit::query()->firstOrCreate(['hospital_id' => $hospital->id, 'patient_id' => $patient->id, 'status' => 'in_encounter'], ['facility_id' => $facility->id, 'department_id' => $department->id, 'clinician_id' => $staff->id, 'source' => 'walk_in', 'checked_in_by' => $admin->id, 'checked_in_at' => now()]);
$encounter = ClinicalEncounter::query()->firstOrCreate(['hospital_id' => $hospital->id, 'patient_id' => $patient->id, 'visit_id' => $visit->id], ['facility_id' => $facility->id, 'responsible_clinician_id' => $staff->id, 'source' => 'walk_in', 'status' => 'in_progress', 'started_by' => $admin->id, 'started_at' => now()]);

$category = BillableServiceCategory::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNB2-MED'], ['name' => 'Cleanup B2 Medicine', 'is_active' => true]);
$service = BillableService::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNB2-MED'], ['billable_service_category_id' => $category->id, 'name' => 'Cleanup B2 Medicine Charge', 'is_tax_exempt' => true, 'tax_rate_basis_points' => 0, 'is_discount_eligible' => true, 'is_active' => true]);
if (! $service->prices()->exists()) {
    app(ServicePricingService::class)->createPrice($service, ['currency' => 'NGN', 'amount_minor' => 500, 'effective_from' => '2026-01-01', 'reason' => 'Smoke price'], $admin);
}

$each = InventoryUnit::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNB2-EA'], ['name' => 'Each', 'base_factor' => 1, 'is_active' => true]);
$item = InventoryItem::query()->firstOrCreate(['hospital_id' => $hospital->id, 'sku' => 'CLNB2-MED'], ['base_unit_id' => $each->id, 'billable_service_id' => $service->id, 'type' => 'medicine', 'name' => 'Cleanup B2 Medicine', 'route' => 'oral', 'reorder_level' => 20, 'requires_pharmacist_validation' => true, 'is_active' => true]);
$supply = InventoryItem::query()->firstOrCreate(['hospital_id' => $hospital->id, 'sku' => 'CLNB2-SUP'], ['base_unit_id' => $each->id, 'type' => 'supply', 'name' => 'Cleanup B2 Supply', 'reorder_level' => 10, 'is_active' => true]);
$main = InventoryLocation::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNB2-MAIN'], ['facility_id' => $facility->id, 'name' => 'Cleanup B2 Main Store', 'type' => 'main_store', 'is_active' => true]);
$pharmacy = InventoryLocation::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNB2-PHAR'], ['facility_id' => $facility->id, 'name' => 'Cleanup B2 Pharmacy Store', 'type' => 'pharmacy', 'is_active' => true]);

$ledger = app(InventoryLedgerService::class);
$batch = InventoryBatch::query()->where('hospital_id', $hospital->id)->where('batch_number', 'CLNB2-BATCH')->first();
if (! $batch) {
    $batch = $ledger->receiveBatch(['hospital_id' => $hospital->id, 'inventory_location_id' => $pharmacy->id, 'inventory_item_id' => $item->id, 'inventory_unit_id' => $each->id, 'batch_number' => 'CLNB2-BATCH', 'expiry_date' => today()->addYear()->toDateString(), 'state' => 'available', 'quantity' => 50, 'reason' => 'Cleanup B2 smoke stock'], $admin);
}

$prescription = Prescription::query()->where('hospital_id', $hospital->id)->where('patient_id', $patient->id)->first();
if (! $prescription) {
    $prescription = app(PrescriptionWorkflowService::class)->createDraft(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'patient_id' => $patient->id, 'clinical_encounter_id' => $encounter->id, 'items' => [['inventory_item_id' => $item->id, 'inventory_unit_id' => $each->id, 'dose' => '1 tablet', 'frequency' => 'Daily', 'duration' => '5 days', 'quantity' => 5, 'instructions' => 'Smoke instructions']]], $admin);
    $prescription = app(PrescriptionWorkflowService::class)->sign($prescription, $admin);
}

$supplier = Supplier::query()->firstOrCreate(['hospital_id' => $hospital->id, 'code' => 'CLNB2-SUP'], ['name' => 'Cleanup B2 Supplier', 'status' => 'active', 'payment_terms' => 'Net 30', 'lead_time_days' => 14]);
$supplier->items()->syncWithPivotValues([$item->id, $supply->id], ['hospital_id' => $hospital->id], false);
ProcurementApprovalLimit::query()->firstOrCreate(['hospital_id' => $hospital->id, 'role_name' => 'admin', 'currency' => 'NGN'], ['limit_minor' => 10000000, 'is_active' => true]);
$workflow = app(ProcurementWorkflowService::class);
$req = PurchaseRequisition::query()->where('hospital_id', $hospital->id)->latest()->first();
if (! $req) {
    $req = $workflow->createRequisition(['hospital_id' => $hospital->id, 'facility_id' => $facility->id, 'inventory_location_id' => $main->id, 'currency' => 'NGN', 'reason' => 'Cleanup B2 reorder', 'lines' => [['inventory_item_id' => $item->id, 'inventory_unit_id' => $each->id, 'quantity' => 10, 'estimated_unit_cost_minor' => 1000, 'discount_minor' => 0, 'tax_minor' => 0]]], $admin);
    $workflow->submit($req, $admin);
}
$req = $req->fresh();
if ($req->status === 'draft') {
    $workflow->submit($req, $admin);
    $req = $req->fresh();
}
if ($req->status === 'submitted') {
    $workflow->approve($req, $approver);
    $req = $req->fresh();
}
if ($req->status === 'approved' && ! $req->converted_purchase_order_id) {
    $workflow->convertToPurchaseOrder($req, $supplier, $approver);
}

File::ensureDirectoryExists(storage_path('app/backend-ui-cleanup-b2'));
File::put(storage_path('app/backend-ui-cleanup-b2/context.json'), json_encode(['email' => $admin->email, 'password' => $password], JSON_PRETTY_PRINT));

echo "Backend UI Cleanup B2 smoke setup ready.\n";
