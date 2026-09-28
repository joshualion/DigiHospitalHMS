<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\InpatientChart;
use App\Models\Patient;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase11BInpatientProductionCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_and_nurse_receive_separate_inpatient_permissions(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $doctor = User::factory()->create(['status' => 'active']);
        $doctor->syncRoles(['doctor']);

        $nurse = User::factory()->create(['status' => 'active']);
        $nurse->syncRoles(['nurse']);

        $this->assertTrue($doctor->can('inpatient.clinical-document'));
        $this->assertTrue($doctor->can('inpatient.nursing-document'));
        $this->assertTrue($doctor->can('inpatient.orders.create'));
        $this->assertTrue($doctor->can('inpatient.orders.execute'));

        $this->assertFalse($nurse->can('inpatient.clinical-document'));
        $this->assertTrue($nurse->can('inpatient.nursing-document'));
        $this->assertFalse($nurse->can('inpatient.orders.create'));
        $this->assertTrue($nurse->can('inpatient.orders.execute'));
        $this->assertFalse($nurse->can('inpatient.discharge-summary.sign'));
    }

    public function test_hospital_admin_can_reconcile_legacy_active_chart_after_discharge(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $hospital = Hospital::factory()->create(['status' => 'active', 'is_active' => true]);
        $facility = Facility::factory()->create([
            'hospital_id' => $hospital->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $admin = User::factory()->create(['access_level' => 'admin', 'status' => 'active']);
        $admin->syncRoles(['hospital-admin']);
        StaffProfile::factory()->create([
            'user_id' => $admin->id,
            'hospital_id' => $hospital->id,
            'staff_category' => 'administrative',
            'employment_status' => 'active',
            'is_active' => true,
        ]);

        $patient = Patient::create([
            'hospital_id' => $hospital->id,
            'registration_facility_id' => $facility->id,
            'registered_by' => $admin->id,
            'hospital_number' => 'P11B-001',
            'first_name' => 'Legacy',
            'last_name' => 'Inpatient',
            'sex' => 'female',
            'status' => 'active',
        ]);

        $admission = Admission::create([
            'hospital_id' => $hospital->id,
            'facility_id' => $facility->id,
            'patient_id' => $patient->id,
            'status' => 'discharged',
            'requested_by' => $admin->id,
            'requested_at' => now()->subDays(2),
            'admitted_at' => now()->subDay(),
            'discharged_at' => now()->subHour(),
        ]);

        $chart = InpatientChart::create([
            'hospital_id' => $hospital->id,
            'facility_id' => $facility->id,
            'admission_id' => $admission->id,
            'patient_id' => $patient->id,
            'status' => 'active',
            'opened_by' => $admin->id,
            'opened_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->post('/admin/inpatient/reconcile')
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $chart->refresh();

        $this->assertSame('closed', $chart->status);
        $this->assertNotNull($chart->closed_at);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'inpatient.chart_reconciled',
        ]);
    }
}
