<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\SensitiveLookup;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase2APatientIdentityTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->hospital = Hospital::factory()->create(['display_name' => 'Identity Hospital']);
        $this->facility = Facility::factory()->create(['hospital_id' => $this->hospital->id, 'is_primary' => true, 'status' => 'active']);
        HospitalSetting::create([
            'hospital_id' => $this->hospital->id,
            'default_facility_id' => $this->facility->id,
            'operating_preferences' => ['patient_duplicate_matching' => ['match_phone' => true, 'match_name_dob' => true, 'match_identifier' => true]],
        ]);
        NumberSequence::create([
            'hospital_id' => $this->hospital->id,
            'key' => 'patient_number',
            'label' => 'Patient hospital number',
            'prefix' => 'PAT',
            'date_format' => null,
            'padding_length' => 4,
            'next_value' => 1,
            'status' => 'active',
        ]);
    }

    public function test_patient_registration_allocates_unique_hospital_number_and_audits(): void
    {
        $user = $this->staffUser(['patients.view', 'patients.register', 'patients.view-sensitive'], 'receptionist');

        $this->actingAs($user)->post('/admin/patients', $this->payload([
            'identifiers' => [['type' => 'NIN', 'value' => '12345678901', 'is_searchable' => true]],
            'contacts' => [['type' => 'next_of_kin', 'name' => 'Jane Doe', 'relationship' => 'Spouse', 'phone' => '08030000001', 'is_next_of_kin' => true]],
        ]))->assertRedirect();

        $patient = Patient::firstOrFail();
        $this->assertSame('PAT-0001', $patient->hospital_number);
        $this->assertSame($this->hospital->id, $patient->hospital_id);
        $this->assertSame('08030000000', $patient->phone);
        $this->assertNotSame('08030000000', DB::table('patients')->whereKey($patient->id)->value('phone_encrypted'));
        $this->assertSame(app(SensitiveLookup::class)->hash('08030000000'), $patient->phone_hash);
        $this->assertDatabaseHas('audit_events', ['action' => 'patients.registered', 'subject_type' => Patient::class, 'subject_id' => $patient->id]);
        $this->assertDatabaseHas('patient_activity_events', ['patient_id' => $patient->id, 'action' => 'registered']);
        $this->assertDatabaseHas('number_sequences', ['hospital_id' => $this->hospital->id, 'next_value' => 2, 'issued_count' => 1]);
    }

    public function test_duplicate_warning_blocks_until_acknowledged_and_never_merges(): void
    {
        $user = $this->staffUser(['patients.view', 'patients.register', 'patients.view-sensitive'], 'receptionist');

        $this->actingAs($user)->post('/admin/patients', $this->payload())->assertRedirect();
        $this->actingAs($user)->post('/admin/patients', $this->payload(['first_name' => 'Johnny']))
            ->assertRedirect()
            ->assertSessionHas('duplicate_warnings');

        $this->assertSame(1, Patient::count());

        $this->actingAs($user)->post('/admin/patients', $this->payload(['first_name' => 'Johnny', 'acknowledge_duplicates' => true]))
            ->assertRedirect();

        $this->assertSame(2, Patient::count());
    }

    public function test_search_by_number_name_phone_and_identifier_is_hospital_scoped(): void
    {
        $user = $this->staffUser(['patients.view', 'patients.register', 'patients.view-sensitive'], 'receptionist');
        $this->actingAs($user)->post('/admin/patients', $this->payload([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'phone' => '08035550000',
            'identifiers' => [['type' => 'Clinic ID', 'value' => 'CL-55', 'is_searchable' => true]],
        ]));

        $otherHospital = Hospital::factory()->create();
        Patient::create([
            'hospital_id' => $otherHospital->id,
            'registration_facility_id' => $this->facility->id,
            'registered_by' => $user->id,
            'hospital_number' => 'OTHER-1',
            'first_name' => 'Ada',
            'last_name' => 'Outside',
            'sex' => 'female',
            'status' => 'active',
        ]);

        foreach (['PAT-0001', 'Lovelace', '08035550000', 'CL-55'] as $term) {
            $this->actingAs($user)->get('/admin/patients?search='.urlencode($term))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Admin/Patients/Index')
                    ->has('patients.data', 1)
                    ->where('patients.data.0.full_name', 'Ada Lovelace'));
        }
    }

    public function test_patient_idor_and_unauthorized_registration_are_rejected(): void
    {
        $viewer = $this->staffUser(['patients.view'], 'nurse');
        $patient = $this->patient();

        $this->actingAs($viewer)->post('/admin/patients', $this->payload())->assertForbidden();

        $otherHospital = Hospital::factory()->create();
        $otherFacility = Facility::factory()->create(['hospital_id' => $otherHospital->id]);
        $other = Patient::create([
            'hospital_id' => $otherHospital->id,
            'registration_facility_id' => $otherFacility->id,
            'registered_by' => $viewer->id,
            'hospital_number' => 'OTHER-2',
            'first_name' => 'Other',
            'last_name' => 'Patient',
            'sex' => 'unknown',
            'status' => 'active',
        ]);

        $this->actingAs($viewer)->get("/admin/patients/{$patient->id}")->assertOk();
        $this->actingAs($viewer)->get("/admin/patients/{$other->id}")->assertForbidden();
    }

    public function test_update_archive_deceased_allergy_alert_and_activity_are_audited(): void
    {
        $user = $this->staffUser(['patients.view', 'patients.update', 'patients.archive', 'patients.record-alerts', 'patients.view-sensitive'], 'doctor');
        $patient = $this->patient();

        $this->actingAs($user)->patch("/admin/patients/{$patient->id}", $this->payload([
            'first_name' => 'Updated',
            'phone' => '08039990000',
            'acknowledge_duplicates' => true,
        ]))->assertRedirect();

        $this->actingAs($user)->post("/admin/patients/{$patient->id}/allergies", [
            'substance' => 'Penicillin',
            'reaction' => 'Rash',
            'severity' => 'moderate',
            'status' => 'active',
            'notes' => 'Reported by patient',
        ])->assertRedirect();

        $this->actingAs($user)->post("/admin/patients/{$patient->id}/alerts", [
            'title' => 'Fall risk',
            'category' => 'safety',
            'severity' => 'high',
            'status' => 'active',
            'notes' => 'Needs assistance',
        ])->assertRedirect();

        $this->actingAs($user)->patch("/admin/patients/{$patient->id}/status", ['status' => 'archived', 'reason' => 'Duplicate entered elsewhere'])->assertRedirect();
        $this->actingAs($user)->patch("/admin/patients/{$patient->id}/status", ['status' => 'deceased', 'reason' => 'Confirmed by family'])->assertRedirect();

        $patient->refresh();
        $this->assertSame('deceased', $patient->status);
        $this->assertNotNull($patient->deceased_at);
        $this->assertDatabaseHas('patient_allergies', ['patient_id' => $patient->id, 'substance' => 'Penicillin', 'recorded_by' => $user->id]);
        $this->assertDatabaseHas('patient_alerts', ['patient_id' => $patient->id, 'title' => 'Fall risk', 'recorded_by' => $user->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'patients.updated', 'subject_type' => Patient::class, 'subject_id' => $patient->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'patients.status.deceased']);
        $this->assertGreaterThanOrEqual(5, $patient->activityEvents()->count());
    }

    public function test_archived_records_are_searchable_but_not_deleted(): void
    {
        $user = $this->staffUser(['patients.view', 'patients.archive'], 'receptionist');
        $patient = $this->patient();

        $this->actingAs($user)->patch("/admin/patients/{$patient->id}/status", ['status' => 'archived', 'reason' => 'Inactive duplicate'])->assertRedirect();

        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'status' => 'archived']);
        $this->actingAs($user)->get('/admin/patients?status=archived')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('patients.data', 1));
    }

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'registration_facility_id' => $this->facility->id,
            'first_name' => 'John',
            'middle_name' => '',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-01',
            'estimated_age_years' => null,
            'is_dob_estimated' => false,
            'sex' => 'male',
            'marital_status' => 'single',
            'occupation' => 'Engineer',
            'address' => '1 Hospital Road',
            'phone' => '08030000000',
            'email' => 'john@example.test',
            'identifiers' => [],
            'contacts' => [],
        ], $overrides);
    }

    private function patient(): Patient
    {
        $patient = Patient::create([
            'hospital_id' => $this->hospital->id,
            'registration_facility_id' => $this->facility->id,
            'registered_by' => $this->staffUser(['patients.view'], 'receptionist')->id,
            'hospital_number' => 'PAT-0099',
            'first_name' => 'Existing',
            'last_name' => 'Patient',
            'date_of_birth' => '1988-02-02',
            'sex' => 'female',
            'status' => 'active',
        ]);
        $patient->phone = '08034440000';
        $patient->save();

        return $patient;
    }

    private function staffUser(array $permissions, string $role): User
    {
        $user = User::factory()->create(['access_level' => 'admin']);
        $user->syncRoles([$role]);
        $user->givePermissionTo($permissions);

        StaffProfile::factory()->create([
            'user_id' => $user->id,
            'hospital_id' => $this->hospital->id,
            'staff_category' => 'administrative',
        ]);

        return $user;
    }
}
