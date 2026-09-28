<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityCrudReassignmentTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;
    private User $admin;
    private Facility $main;
    private Facility $legacy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->hospital = Hospital::factory()->create();

        $this->main = Facility::factory()->create([
            'hospital_id' => $this->hospital->id,
            'name' => 'Main Facility',
            'code' => 'MAIN',
            'is_primary' => true,
            'status' => 'active',
        ]);

        $this->legacy = Facility::factory()->create([
            'hospital_id' => $this->hospital->id,
            'name' => 'Phase Test Facility',
            'code' => 'PHASE-X',
            'is_primary' => false,
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create(['access_level' => 'admin']);
        $this->admin->syncRoles(['hospital-admin']);

        StaffProfile::factory()->create([
            'user_id' => $this->admin->id,
            'hospital_id' => $this->hospital->id,
            'staff_category' => 'administrative',
            'is_active' => true,
            'employment_status' => 'active',
        ]);
    }

    public function test_primary_facility_deactivation_returns_inline_validation_error(): void
    {
        $response = $this->actingAs($this->admin)->patch("/admin/facilities/{$this->main->id}/status", [
            'status' => 'inactive',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('facility');
        $this->assertSame('active', $this->main->fresh()->status);
    }

    public function test_unused_non_primary_facility_can_be_deleted(): void
    {
        $this->actingAs($this->admin)
            ->delete("/admin/facilities/{$this->legacy->id}")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('facilities', ['id' => $this->legacy->id]);
    }

    public function test_linked_records_can_be_reassigned_before_facility_deletion(): void
    {
        $patient = Patient::create([
            'hospital_id' => $this->hospital->id,
            'registration_facility_id' => $this->legacy->id,
            'registered_by' => $this->admin->id,
            'hospital_number' => 'PAT-FAC-001',
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'sex' => 'female',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->delete("/admin/facilities/{$this->legacy->id}", [
            'replacement_facility_id' => $this->main->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('facilities', ['id' => $this->legacy->id]);
        $this->assertSame($this->main->id, $patient->fresh()->registration_facility_id);
        $this->assertDatabaseHas('audit_events', ['action' => 'facilities.deleted_with_reassignment']);
    }
}
