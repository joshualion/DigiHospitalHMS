<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Hospital;
use App\Models\LabSpecimenType;
use App\Models\LabTest;
use App\Models\LabTestProfile;
use App\Models\LabUnit;
use App\Models\Patient;
use App\Models\RadiologyModality;
use App\Models\RadiologyStudy;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase11CDiagnosticsProductionCompletionTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;
    private Facility $facility;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->hospital = Hospital::factory()->create(['status' => 'active', 'is_active' => true]);
        $this->facility = Facility::factory()->create([
            'hospital_id' => $this->hospital->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->admin = User::factory()->create(['access_level' => 'admin', 'status' => 'active']);
        $this->admin->syncRoles(['hospital-admin']);

        StaffProfile::factory()->create([
            'user_id' => $this->admin->id,
            'hospital_id' => $this->hospital->id,
            'staff_category' => 'administrative',
            'employment_status' => 'active',
            'is_active' => true,
        ]);
    }

    public function test_diagnostics_catalogues_support_update_and_safe_delete_for_unused_items(): void
    {
        $specimen = LabSpecimenType::create([
            'hospital_id' => $this->hospital->id,
            'code' => 'SER',
            'name' => 'Serum',
            'is_active' => true,
        ]);

        $unit = LabUnit::create([
            'hospital_id' => $this->hospital->id,
            'code' => 'MMOL',
            'name' => 'mmol/L',
            'is_active' => true,
        ]);

        $test = LabTest::create([
            'hospital_id' => $this->hospital->id,
            'default_specimen_type_id' => $specimen->id,
            'code' => 'GLU',
            'name' => 'Glucose',
            'requires_approval' => true,
            'is_active' => true,
        ]);

        $profile = LabTestProfile::create([
            'hospital_id' => $this->hospital->id,
            'code' => 'CHEM',
            'name' => 'Chemistry Panel',
            'is_active' => true,
        ]);
        $profile->tests()->sync([$test->id]);

        $modality = RadiologyModality::create([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'code' => 'XR',
            'name' => 'X-Ray',
            'is_active' => true,
        ]);

        $study = RadiologyStudy::create([
            'hospital_id' => $this->hospital->id,
            'radiology_modality_id' => $modality->id,
            'code' => 'CXR',
            'name' => 'Chest X-Ray',
            'requires_professional_validation' => true,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->patch("/admin/laboratory/specimen-types/{$specimen->id}", [
                'code' => 'SER',
                'name' => 'Serum Updated',
                'collection_notes' => 'Updated collection notes.',
                'is_active' => false,
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->patch("/admin/laboratory/units/{$unit->id}", [
                'code' => 'MMOL',
                'name' => 'Millimoles per litre',
                'is_active' => false,
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->patch("/admin/laboratory/tests/{$test->id}", [
                'department_id' => null,
                'default_specimen_type_id' => $specimen->id,
                'billable_service_id' => null,
                'code' => 'GLU',
                'name' => 'Blood Glucose',
                'description' => 'Updated',
                'turnaround_time' => '2 hours',
                'requires_approval' => true,
                'is_active' => false,
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->patch("/admin/laboratory/profiles/{$profile->id}", [
                'code' => 'CHEM',
                'name' => 'Chemistry Panel Updated',
                'description' => 'Updated',
                'lab_test_ids' => [$test->id],
                'is_active' => false,
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->patch("/admin/radiology/modalities/{$modality->id}", [
                'facility_id' => $this->facility->id,
                'code' => 'XR',
                'name' => 'Digital X-Ray',
                'description' => 'Updated',
                'is_active' => false,
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->patch("/admin/radiology/studies/{$study->id}", [
                'radiology_modality_id' => $modality->id,
                'billable_service_id' => null,
                'code' => 'CXR',
                'name' => 'Chest Radiograph',
                'description' => 'Updated',
                'preparation_acknowledgements' => [],
                'safety_screening_acknowledgements' => [],
                'requires_professional_validation' => true,
                'is_active' => false,
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Serum Updated', $specimen->fresh()->name);
        $this->assertFalse($specimen->fresh()->is_active);
        $this->assertSame('Blood Glucose', $test->fresh()->name);
        $this->assertFalse($study->fresh()->is_active);

        $this->actingAs($this->admin)->delete("/admin/laboratory/profiles/{$profile->id}")->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->delete("/admin/laboratory/tests/{$test->id}")->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->delete("/admin/laboratory/units/{$unit->id}")->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->delete("/admin/laboratory/specimen-types/{$specimen->id}")->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->delete("/admin/radiology/studies/{$study->id}")->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->delete("/admin/radiology/modalities/{$modality->id}")->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('lab_tests', ['id' => $test->id]);
        $this->assertDatabaseMissing('lab_specimen_types', ['id' => $specimen->id]);
        $this->assertDatabaseMissing('radiology_studies', ['id' => $study->id]);
        $this->assertDatabaseMissing('radiology_modalities', ['id' => $modality->id]);
    }

    public function test_clinically_used_diagnostics_catalogue_items_are_protected_from_hard_delete(): void
    {
        $patient = Patient::create([
            'hospital_id' => $this->hospital->id,
            'registration_facility_id' => $this->facility->id,
            'registered_by' => $this->admin->id,
            'hospital_number' => 'P11C-001',
            'first_name' => 'Diagnostic',
            'last_name' => 'Patient',
            'sex' => 'female',
            'status' => 'active',
        ]);

        $specimen = LabSpecimenType::create([
            'hospital_id' => $this->hospital->id,
            'code' => 'BLD',
            'name' => 'Blood',
            'is_active' => true,
        ]);

        $test = LabTest::create([
            'hospital_id' => $this->hospital->id,
            'default_specimen_type_id' => $specimen->id,
            'code' => 'FBC',
            'name' => 'Full Blood Count',
            'requires_approval' => true,
            'is_active' => true,
        ]);

        $labRequestId = DB::table('lab_requests')->insertGetId([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $patient->id,
            'ordered_by' => $this->admin->id,
            'request_number' => 'LAB-P11C-001',
            'accession_number' => 'ACC-P11C-001',
            'status' => 'ordered',
            'priority' => 'routine',
            'ordered_at' => now(),
        ]);

        DB::table('lab_request_tests')->insert([
            'hospital_id' => $this->hospital->id,
            'lab_request_id' => $labRequestId,
            'lab_test_id' => $test->id,
            'test_code' => $test->code,
            'test_name' => $test->name,
            'status' => 'ordered',
        ]);

        DB::table('lab_specimens')->insert([
            'hospital_id' => $this->hospital->id,
            'lab_request_id' => $labRequestId,
            'lab_specimen_type_id' => $specimen->id,
            'label_number' => 'SPEC-P11C-001',
            'status' => 'collected',
        ]);

        $modality = RadiologyModality::create([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'code' => 'US',
            'name' => 'Ultrasound',
            'is_active' => true,
        ]);

        $study = RadiologyStudy::create([
            'hospital_id' => $this->hospital->id,
            'radiology_modality_id' => $modality->id,
            'code' => 'ABDUS',
            'name' => 'Abdominal Ultrasound',
            'requires_professional_validation' => true,
            'is_active' => true,
        ]);

        $radiologyRequestId = DB::table('radiology_requests')->insertGetId([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $patient->id,
            'ordered_by' => $this->admin->id,
            'request_number' => 'RAD-P11C-001',
            'accession_number' => 'RACC-P11C-001',
            'status' => 'ordered',
            'priority' => 'routine',
            'clinical_indication' => 'Configured indication',
            'ordered_at' => now(),
        ]);

        DB::table('radiology_request_studies')->insert([
            'hospital_id' => $this->hospital->id,
            'radiology_request_id' => $radiologyRequestId,
            'radiology_study_id' => $study->id,
            'study_code' => $study->code,
            'study_name' => $study->name,
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/laboratory/tests/{$test->id}")
            ->assertRedirect()
            ->assertSessionHasErrors('catalogue');

        $this->actingAs($this->admin)
            ->delete("/admin/laboratory/specimen-types/{$specimen->id}")
            ->assertRedirect()
            ->assertSessionHasErrors('catalogue');

        $this->actingAs($this->admin)
            ->delete("/admin/radiology/studies/{$study->id}")
            ->assertRedirect()
            ->assertSessionHasErrors('catalogue');

        $this->actingAs($this->admin)
            ->delete("/admin/radiology/modalities/{$modality->id}")
            ->assertRedirect()
            ->assertSessionHasErrors('catalogue');

        $this->assertDatabaseHas('lab_tests', ['id' => $test->id]);
        $this->assertDatabaseHas('lab_specimen_types', ['id' => $specimen->id]);
        $this->assertDatabaseHas('radiology_studies', ['id' => $study->id]);
        $this->assertDatabaseHas('radiology_modalities', ['id' => $modality->id]);
    }
}
