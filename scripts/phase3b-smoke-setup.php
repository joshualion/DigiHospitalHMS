<?php

use App\Models\CashierShift;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\PaymentMethod;
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

$cashierEmail = getenv('PHASE3B_CASHIER_EMAIL') ?: 'phase3b-cashier@example.test';
$accountantEmail = getenv('PHASE3B_ACCOUNTANT_EMAIL') ?: 'phase3b-accountant@example.test';
$password = getenv('PHASE3B_PASSWORD') ?: 'Phase3BSmoke!';
$unique = time();

$hospital = Hospital::primary() ?? Hospital::query()->firstOrCreate(
    ['legal_name' => 'Phase 3B Smoke Hospital'],
    ['display_name' => 'Phase 3B Smoke Hospital', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'default_currency' => 'NGN'],
);

$facility = Facility::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'code' => 'P3B'],
    ['name' => 'Phase 3B Cashier Clinic', 'facility_type' => 'clinic', 'city' => 'Lagos', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'is_primary' => true, 'status' => 'active'],
);
HospitalSetting::query()->updateOrCreate(['hospital_id' => $hospital->id], ['default_facility_id' => $facility->id, 'currency' => 'NGN']);
NumberSequence::query()->firstOrCreate(
    ['hospital_id' => $hospital->id, 'facility_id' => null, 'key' => 'receipt_number'],
    ['label' => 'Receipt number', 'prefix' => 'RCT', 'date_format' => 'Y', 'padding_length' => 6, 'next_value' => 1, 'issued_count' => 0, 'status' => 'active'],
);

$cashier = smokeUser($cashierEmail, $password, 'Phase3B', 'Cashier', 'cashier', [
    'hospital.view', 'facilities.view', 'departments.view', 'patients.view', 'invoices.view',
    'payments.view', 'payments.post', 'refunds.request', 'cashier-shifts.open', 'cashier-shifts.close',
], $hospital);
$accountant = smokeUser($accountantEmail, $password, 'Phase3B', 'Accountant', 'accountant', [
    'hospital.view', 'facilities.view', 'departments.view', 'patients.view', 'invoices.view',
    'payments.view', 'payments.reverse', 'refunds.approve', 'refunds.process', 'cashier-shifts.review',
], $hospital);

CashierShift::query()
    ->where('hospital_id', $hospital->id)
    ->where('cashier_id', $cashier->id)
    ->where('status', 'open')
    ->update(['status' => 'closed', 'closed_at' => now(), 'counted_cash_minor' => 0, 'variance_minor' => 0]);

foreach ([
    ['code' => 'CASH', 'name' => 'Cash', 'type' => 'cash', 'requires_open_shift' => true],
    ['code' => 'TRANSFER', 'name' => 'Bank transfer', 'type' => 'transfer', 'requires_open_shift' => false],
] as $method) {
    PaymentMethod::query()->updateOrCreate(
        ['hospital_id' => $hospital->id, 'code' => $method['code']],
        $method + ['reference_fields' => [], 'is_active' => true],
    );
}

$patient = Patient::query()->create([
    'hospital_id' => $hospital->id,
    'registration_facility_id' => $facility->id,
    'registered_by' => $cashier->id,
    'hospital_number' => "P3B-{$unique}",
    'first_name' => 'Phase3B',
    'last_name' => 'Patient',
    'date_of_birth' => '1990-01-01',
    'sex' => 'female',
    'status' => 'active',
]);
$patient->phone = '08050000001';
$patient->save();

smokeInvoice($hospital, $facility, $patient, $accountant, "P3B-PART-{$unique}", 10000);
smokeInvoice($hospital, $facility, $patient, $accountant, "P3B-FULL-{$unique}", 4000);

echo "Phase 3B smoke setup ready for {$cashierEmail} and {$accountantEmail}\n";

function smokeUser(string $email, string $password, string $firstname, string $lastname, string $role, array $permissions, Hospital $hospital): User
{
    $user = User::query()->updateOrCreate(
        ['email' => $email],
        ['firstname' => $firstname, 'lastname' => $lastname, 'password' => Hash::make($password), 'access_level' => 'admin', 'status' => 'active'],
    );
    $user->forceFill(['email_verified_at' => now()])->save();
    $user->syncRoles([$role]);
    $user->givePermissionTo($permissions);
    StaffProfile::query()->updateOrCreate(
        ['user_id' => $user->id],
        ['hospital_id' => $hospital->id, 'staff_number' => strtoupper($role).'-P3B', 'job_title' => $role, 'staff_category' => 'administrative', 'employment_status' => 'active', 'is_active' => true],
    );

    return $user;
}

function smokeInvoice(Hospital $hospital, Facility $facility, Patient $patient, User $accountant, string $number, int $amount): void
{
    $invoice = Invoice::query()->create([
        'hospital_id' => $hospital->id,
        'facility_id' => $facility->id,
        'patient_id' => $patient->id,
        'invoice_number' => $number,
        'status' => 'issued',
        'currency' => 'NGN',
        'subtotal_minor' => $amount,
        'total_minor' => $amount,
        'balance_minor' => $amount,
        'payment_status' => 'unpaid',
        'created_by' => $accountant->id,
        'issued_by' => $accountant->id,
        'issued_at' => now(),
    ]);
    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'hospital_id' => $hospital->id,
        'line_type' => 'manual',
        'service_name' => 'Smoke billing item',
        'quantity' => 1,
        'unit_price_minor' => $amount,
        'subtotal_minor' => $amount,
        'total_minor' => $amount,
        'manual_reason' => 'Phase 3B smoke test',
        'created_by' => $accountant->id,
    ]);
}
