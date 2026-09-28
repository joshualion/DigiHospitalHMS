<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\AdmissionBedMovement;
use App\Models\Bed;
use App\Models\BedClass;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\PatientActivityEvent;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardRoom;
use App\Services\AdmissionIntegrityService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase11AAdmissionsProductionCompletionTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;
    private Facility $facility;
    private Department $department;
    private User $admin;
    private BedClass $bedClass;
    private Ward $ward;
    private WardRoom $room;
    private Bed $bed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->hospital = Hospital::factory()->create([
            'display_name' => 'Production Hospital',
            'is_active' => true,
            'status' => 'active',
        ]);

        $this->facility = Facility::factory()->create([
            'hospital_id' => $this->hospital->id,
            'name' => 'Main Facility',
            'code' => 'MAIN',
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->department = Department::create([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'code' => 'MED',
            'name' => 'Medicine',
            'category' => 'clinical',
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create([
            'access_level' => 'admin',
            'status' => 'active',
        ]);
        $this->admin->syncRoles(['hospital-admin']);

        StaffProfile::factory()->create([
            'user_id' => $this->admin->id,
            'hospital_id' => $this->hospital->id,
            'staff_category' => 'administrative',
            'employment_status' => 'active',
            'is_active' => true,
        ]);

        $this->bedClass = BedClass::create([
            'hospital_id' => $this->hospital->id,
            'code' => 'GEN',
            'name' => 'General',
            'is_active' => true,
        ]);

        $this->ward = Ward::create([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'department_id' => $this->department->id,
            'code' => 'WARD',
            'name' => 'General Ward',
            'status' => 'active',
        ]);

        $this->room = WardRoom::create([
            'hospital_id' => $this->hospital->id,
            'ward_id' => $this->ward->id,
            'code' => 'R1',
            'name' => 'Room 1',
            'status' => 'active',
        ]);

        $this->bed = Bed::create([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'ward_id' => $this->ward->id,
            'ward_room_id' => $this->room->id,
            'bed_class_id' => $this->bedClass->id,
            'code' => 'B1',
            'label' => 'Bed 1',
            'state' => 'available',
        ]);
    }

    public function test_stale_occupied_bed_is_not_counted_as_real_occupancy_and_can_be_reconciled(): void
    {
        $this->bed->update(['state' => 'occupied', 'state_reason' => 'Old demo state']);

        $state = app(AdmissionIntegrityService::class)->bedBoard($this->hospital->id);
        $bed = $state['beds']->firstWhere('id', $this->bed->id);

        $this->assertSame('available', $bed->effective_state);
        $this->assertNotNull($bed->integrity_issue);
        $this->assertSame(0, collect($state['census'])->firstWhere('state', 'occupied')['count'] ?? 0);

        $this->actingAs($this->admin)
            ->post('/admin/admissions/reconcile-beds')
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('available', $this->bed->fresh()->state);
        $this->assertDatabaseHas('audit_events', ['action' => 'admissions.bed_reconciled']);
    }

    public function test_active_admission_is_authoritative_for_occupied_state(): void
    {
        $patient = $this->patient('PAT-11A-001');

        Admission::create([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $patient->id,
            'department_id' => $this->department->id,
            'current_ward_id' => $this->ward->id,
            'current_bed_id' => $this->bed->id,
            'status' => 'admitted',
            'requested_by' => $this->admin->id,
            'requested_at' => now(),
            'admitted_at' => now(),
        ]);

        $state = app(AdmissionIntegrityService::class)->bedBoard($this->hospital->id);
        $bed = $state['beds']->firstWhere('id', $this->bed->id);

        $this->assertSame('occupied', $bed->effective_state);
        $this->assertNotNull($bed->integrity_issue);

        $this->actingAs($this->admin)->post('/admin/admissions/reconcile-beds')->assertRedirect();

        $this->assertSame('occupied', $this->bed->fresh()->state);
    }

    public function test_admissions_setup_has_update_and_safe_delete_crud(): void
    {
        $this->actingAs($this->admin)
            ->patch("/admin/admissions/bed-classes/{$this->bedClass->id}", [
                'code' => 'GEN',
                'name' => 'General Updated',
                'billable_service_id' => null,
                'description' => 'Updated',
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->patch("/admin/admissions/wards/{$this->ward->id}", [
                'facility_id' => $this->facility->id,
                'department_id' => $this->department->id,
                'code' => 'WARD',
                'name' => 'Ward Updated',
                'notes' => 'Updated',
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->patch("/admin/admissions/rooms/{$this->room->id}", [
                'ward_id' => $this->ward->id,
                'code' => 'R1',
                'name' => 'Room Updated',
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->patch("/admin/admissions/beds/{$this->bed->id}", [
                'ward_id' => $this->ward->id,
                'ward_room_id' => $this->room->id,
                'bed_class_id' => $this->bedClass->id,
                'code' => 'B1',
                'label' => 'Bed Updated',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('General Updated', $this->bedClass->fresh()->name);
        $this->assertSame('Ward Updated', $this->ward->fresh()->name);
        $this->assertSame('Room Updated', $this->room->fresh()->name);
        $this->assertSame('Bed Updated', $this->bed->fresh()->label);

        $this->actingAs($this->admin)->delete("/admin/admissions/beds/{$this->bed->id}")->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->delete("/admin/admissions/rooms/{$this->room->id}")->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->delete("/admin/admissions/wards/{$this->ward->id}")->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->delete("/admin/admissions/bed-classes/{$this->bedClass->id}")->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('beds', ['id' => $this->bed->id]);
        $this->assertDatabaseMissing('ward_rooms', ['id' => $this->room->id]);
        $this->assertDatabaseMissing('wards', ['id' => $this->ward->id]);
        $this->assertDatabaseMissing('bed_classes', ['id' => $this->bedClass->id]);
    }

    public function test_preproduction_reset_clears_admissions_setup_and_history_but_preserves_patient_and_facility(): void
    {
        $patient = $this->patient('PAT-11A-DEMO');

        $admission = Admission::create([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $patient->id,
            'department_id' => $this->department->id,
            'current_ward_id' => $this->ward->id,
            'current_bed_id' => $this->bed->id,
            'status' => 'admitted',
            'requested_by' => $this->admin->id,
            'requested_at' => now(),
            'admitted_at' => now(),
        ]);

        AdmissionBedMovement::create([
            'hospital_id' => $this->hospital->id,
            'admission_id' => $admission->id,
            'to_facility_id' => $this->facility->id,
            'to_department_id' => $this->department->id,
            'to_ward_id' => $this->ward->id,
            'to_bed_id' => $this->bed->id,
            'movement_type' => 'admit',
            'started_at' => now(),
            'performed_by' => $this->admin->id,
        ]);

        PatientActivityEvent::create([
            'patient_id' => $patient->id,
            'hospital_id' => $this->hospital->id,
            'actor_id' => $this->admin->id,
            'action' => 'admission.admitted',
            'metadata' => ['admission_id' => $admission->id],
            'occurred_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->delete('/admin/admissions/purge-demo', ['confirmation' => 'PURGE ADMISSIONS'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('admissions', ['hospital_id' => $this->hospital->id]);
        $this->assertDatabaseMissing('admission_bed_movements', ['hospital_id' => $this->hospital->id]);
        $this->assertDatabaseMissing('patient_activity_events', ['hospital_id' => $this->hospital->id, 'action' => 'admission.admitted']);
        $this->assertDatabaseMissing('beds', ['hospital_id' => $this->hospital->id]);
        $this->assertDatabaseMissing('ward_rooms', ['hospital_id' => $this->hospital->id]);
        $this->assertDatabaseMissing('wards', ['hospital_id' => $this->hospital->id]);
        $this->assertDatabaseMissing('bed_classes', ['hospital_id' => $this->hospital->id]);

        $this->assertDatabaseHas('patients', ['id' => $patient->id]);
        $this->assertDatabaseHas('facilities', ['id' => $this->facility->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'admissions.demo_reset']);
    }

    private function patient(string $number): Patient
    {
        return Patient::create([
            'hospital_id' => $this->hospital->id,
            'registration_facility_id' => $this->facility->id,
            'registered_by' => $this->admin->id,
            'hospital_number' => $number,
            'first_name' => 'Phase',
            'last_name' => 'Eleven',
            'sex' => 'female',
            'status' => 'active',
        ]);
    }
}
