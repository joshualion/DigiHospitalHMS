<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\PublicAppointmentRequest;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffDoctorAppointmentManagementTest extends TestCase
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

        $this->hospital = Hospital::factory()->create(['is_active' => true, 'status' => 'active']);
        $this->facility = Facility::factory()->create([
            'hospital_id' => $this->hospital->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create(['access_level' => 'admin', 'status' => 'active']);
        $this->admin->syncRoles(['hospital-admin']);
        StaffProfile::factory()->create([
            'user_id' => $this->admin->id,
            'hospital_id' => $this->hospital->id,
            'staff_category' => 'administrative',
            'is_active' => true,
            'employment_status' => 'active',
        ]);
    }

    private function doctor(string $email = 'doctor@example.test'): StaffProfile
    {
        $user = User::factory()->create(['email' => $email, 'access_level' => 'patient', 'status' => 'active']);
        $user->syncRoles(['doctor']);

        return StaffProfile::factory()->create([
            'user_id' => $user->id,
            'hospital_id' => $this->hospital->id,
            'staff_category' => 'doctor',
            'job_title' => 'Consultant Doctor',
            'is_active' => true,
            'employment_status' => 'active',
            'public_is_visible' => true,
        ]);
    }

    public function test_unused_seeded_staff_account_can_be_hard_deleted(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $staff = StaffProfile::factory()->create([
            'user_id' => $user->id,
            'hospital_id' => $this->hospital->id,
            'staff_category' => 'administrative',
            'is_active' => true,
            'employment_status' => 'active',
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/staff/{$staff->id}")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('staff_profiles', ['id' => $staff->id]);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_staff_with_historical_appointment_is_not_hard_deleted(): void
    {
        $doctor = $this->doctor('linked-doctor@example.test');
        $patient = Patient::create([
            'hospital_id' => $this->hospital->id,
            'registration_facility_id' => $this->facility->id,
            'registered_by' => $this->admin->id,
            'hospital_number' => 'PAT-STAFF-001',
            'first_name' => 'Linked',
            'last_name' => 'Patient',
            'sex' => 'female',
            'status' => 'active',
        ]);
        $type = AppointmentType::create([
            'hospital_id' => $this->hospital->id,
            'name' => 'Consultation',
            'code' => 'CONS-STAFF',
            'duration_minutes' => 30,
            'is_active' => true,
        ]);
        Appointment::create([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $patient->id,
            'clinician_id' => $doctor->id,
            'appointment_type_id' => $type->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addMinutes(30),
            'status' => 'scheduled',
            'source' => 'staff',
            'booked_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/staff/{$doctor->id}")
            ->assertRedirect()
            ->assertSessionHasErrors('staff');

        $this->assertDatabaseHas('staff_profiles', ['id' => $doctor->id]);
        $this->assertDatabaseHas('users', ['id' => $doctor->user_id]);
    }


    public function test_hospital_admin_can_purge_demo_staff_with_restrict_linked_records(): void
    {
        $doctor = $this->doctor('phase7b-auth@example.test');
        $patient = Patient::create([
            'hospital_id' => $this->hospital->id,
            'registration_facility_id' => $this->facility->id,
            'registered_by' => $this->admin->id,
            'hospital_number' => 'PAT-DEMO-PURGE-001',
            'first_name' => 'Demo',
            'last_name' => 'Patient',
            'sex' => 'female',
            'status' => 'active',
        ]);
        $type = AppointmentType::create([
            'hospital_id' => $this->hospital->id,
            'name' => 'Demo Consultation',
            'code' => 'DEMO-PURGE',
            'duration_minutes' => 30,
            'is_active' => true,
        ]);
        $appointment = Appointment::create([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $patient->id,
            'clinician_id' => $doctor->id,
            'appointment_type_id' => $type->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addMinutes(30),
            'status' => 'scheduled',
            'source' => 'staff',
            'booked_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/staff/{$doctor->id}/purge-demo", ['confirmation' => 'PURGE'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('appointments', ['id' => $appointment->id]);
        $this->assertDatabaseMissing('staff_profiles', ['id' => $doctor->id]);
        $this->assertDatabaseMissing('users', ['id' => $doctor->user_id]);
        $this->assertDatabaseHas('patients', ['id' => $patient->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'staff.demo_purged']);
    }

    public function test_demo_purge_clears_nullable_reviewer_references_instead_of_deleting_unrelated_record(): void
    {
        $doctor = $this->doctor('phase7a-verifier@example.test');

        $publicRequest = PublicAppointmentRequest::create([
            'hospital_id' => $this->hospital->id,
            'name' => 'Keep This Request',
            'consent' => true,
            'status' => 'accepted',
            'preferred_clinician_id' => $doctor->id,
            'reviewed_by' => $doctor->user_id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/staff/{$doctor->id}/purge-demo", ['confirmation' => 'PURGE'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('public_appointment_requests', [
            'id' => $publicRequest->id,
            'preferred_clinician_id' => null,
            'reviewed_by' => null,
        ]);
    }

    public function test_demo_purge_requires_explicit_confirmation(): void
    {
        $doctor = $this->doctor('phase6a-admin@example.test');

        $this->actingAs($this->admin)
            ->delete("/admin/staff/{$doctor->id}/purge-demo", ['confirmation' => 'DELETE'])
            ->assertSessionHasErrors('confirmation');

        $this->assertDatabaseHas('staff_profiles', ['id' => $doctor->id]);
        $this->assertDatabaseHas('users', ['id' => $doctor->user_id]);
    }

    public function test_public_request_can_target_a_doctor_and_only_that_doctor_can_review_it(): void
    {
        $doctor = $this->doctor('preferred-doctor@example.test');
        $otherDoctor = $this->doctor('other-doctor@example.test');

        $response = $this->post('/appointment/request', [
            'name' => 'Public Patient',
            'phone' => '08000000000',
            'preferred_facility_id' => $this->facility->id,
            'preferred_clinician_id' => $doctor->id,
            'preferred_date' => today()->addDay()->toDateString(),
            'consent' => true,
        ]);

        $response->assertRedirect();

        $request = PublicAppointmentRequest::firstOrFail();
        $this->assertSame($doctor->id, $request->preferred_clinician_id);

        $this->actingAs($otherDoctor->user)
            ->patch("/admin/appointment-requests/{$request->id}", [
                'status' => 'accepted',
                'reason' => 'Accept',
            ])
            ->assertForbidden();

        $this->actingAs($doctor->user)
            ->patch("/admin/appointment-requests/{$request->id}", [
                'status' => 'accepted',
                'reason' => 'Doctor accepted request',
            ])
            ->assertRedirect();

        $this->assertSame('accepted', $request->fresh()->status);
        $this->assertSame($doctor->user_id, $request->fresh()->reviewed_by);
    }

    public function test_doctor_can_confirm_own_appointment_but_not_another_doctors(): void
    {
        $doctor = $this->doctor('own-doctor@example.test');
        $otherDoctor = $this->doctor('second-doctor@example.test');
        $patient = Patient::create([
            'hospital_id' => $this->hospital->id,
            'registration_facility_id' => $this->facility->id,
            'registered_by' => $this->admin->id,
            'hospital_number' => 'PAT-DOC-001',
            'first_name' => 'Doctor',
            'last_name' => 'Booking',
            'sex' => 'male',
            'status' => 'active',
        ]);
        $type = AppointmentType::create([
            'hospital_id' => $this->hospital->id,
            'name' => 'Doctor Consultation',
            'code' => 'DOC-CONS',
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $own = Appointment::create([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $patient->id,
            'clinician_id' => $doctor->id,
            'appointment_type_id' => $type->id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addMinutes(30),
            'status' => 'scheduled',
            'source' => 'staff',
            'booked_by' => $this->admin->id,
        ]);

        $other = Appointment::create([
            'hospital_id' => $this->hospital->id,
            'facility_id' => $this->facility->id,
            'patient_id' => $patient->id,
            'clinician_id' => $otherDoctor->id,
            'appointment_type_id' => $type->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addMinutes(30),
            'status' => 'scheduled',
            'source' => 'staff',
            'booked_by' => $this->admin->id,
        ]);

        $this->actingAs($doctor->user)
            ->patch("/admin/appointments/{$own->id}/transition", ['action' => 'confirm'])
            ->assertRedirect();

        $this->assertSame('confirmed', $own->fresh()->status);

        $this->actingAs($doctor->user)
            ->patch("/admin/appointments/{$other->id}/transition", ['action' => 'confirm'])
            ->assertForbidden();

        $this->assertSame('scheduled', $other->fresh()->status);
    }
}
