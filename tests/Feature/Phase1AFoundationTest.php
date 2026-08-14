<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Facility;
use App\Models\FacilityMembership;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\NumberSequence;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\AuditService;
use App\Services\NumberSequenceService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase1AFoundationTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;

    private Facility $facility;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->hospital = Hospital::factory()->create();
        $this->facility = Facility::factory()->create([
            'hospital_id' => $this->hospital->id,
            'code' => 'MAIN',
            'is_primary' => true,
        ]);

        HospitalSetting::create([
            'hospital_id' => $this->hospital->id,
            'default_facility_id' => $this->facility->id,
        ]);
    }

    public function test_hospital_profile_can_be_updated_and_audited(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->patch('/admin/hospital', [
                'legal_name' => 'Updated Legal Name',
                'display_name' => 'Updated Hospital',
                'country' => 'Nigeria',
                'timezone' => 'Africa/Lagos',
                'status' => 'active',
                'default_currency' => 'NGN',
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Updated Hospital', $this->hospital->refresh()->display_name);
        $this->assertDatabaseHas('audit_events', ['action' => 'hospital.updated', 'subject_id' => $this->hospital->id]);
    }

    public function test_facility_creation_enforces_scoped_codes_and_primary_facility_rule(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post('/admin/facilities', [
                'name' => 'Second Branch',
                'code' => 'BR2',
                'facility_type' => 'branch',
                'country' => 'Nigeria',
                'is_primary' => true,
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse($this->facility->refresh()->is_primary);
        $this->assertDatabaseHas('facilities', ['hospital_id' => $this->hospital->id, 'code' => 'BR2', 'is_primary' => true]);
        $this->assertDatabaseHas('audit_events', ['action' => 'facilities.created']);

        $this->actingAs($admin)
            ->post('/admin/facilities', [
                'name' => 'Duplicate Branch',
                'code' => 'BR2',
                'facility_type' => 'branch',
                'country' => 'Nigeria',
                'is_primary' => false,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_department_management_supports_hospital_wide_and_facility_specific_departments(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post('/admin/departments', [
                'facility_id' => null,
                'name' => 'Administration',
                'code' => 'ADMIN',
                'category' => 'administrative',
                'status' => 'active',
                'display_order' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('departments', [
            'hospital_id' => $this->hospital->id,
            'facility_id' => null,
            'code' => 'ADMIN',
        ]);
        $this->assertDatabaseHas('audit_events', ['action' => 'departments.created']);
    }

    public function test_staff_can_be_invited_with_roles_and_facility_membership(): void
    {
        Notification::fake();
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->post('/admin/staff', [
                'firstname' => 'Ada',
                'lastname' => 'Care',
                'email' => 'ada@example.test',
                'staff_number' => 'STF-001',
                'job_title' => 'Reception Lead',
                'staff_category' => 'administrative',
                'roles' => ['receptionist'],
                'facility_ids' => [$this->facility->id],
                'default_facility_id' => $this->facility->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'ada@example.test')->firstOrFail();

        $this->assertTrue($user->hasRole('receptionist'));
        $this->assertDatabaseHas('staff_profiles', ['user_id' => $user->id, 'staff_number' => 'STF-001']);
        $this->assertDatabaseHas('facility_memberships', ['facility_id' => $this->facility->id, 'is_default' => true]);
        $this->assertDatabaseHas('audit_events', ['action' => 'staff.invited']);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_non_admin_and_cross_hospital_idor_access_are_rejected(): void
    {
        $ordinary = User::factory()->create();
        $ordinary->assignRole('patient');

        $this->actingAs($ordinary)->get('/admin/facilities')->assertForbidden();

        $admin = $this->adminUser();
        $otherHospital = Hospital::factory()->create();
        $otherFacility = Facility::factory()->create(['hospital_id' => $otherHospital->id]);

        $this->actingAs($admin)
            ->patch("/admin/facilities/{$otherFacility->id}", [
                'name' => 'Other',
                'code' => 'OTH',
                'facility_type' => 'branch',
                'country' => 'Nigeria',
                'is_primary' => false,
                'status' => 'active',
            ])
            ->assertForbidden();
    }

    public function test_final_superadministrator_cannot_be_suspended(): void
    {
        $superadmin = $this->adminUser('superadmin');

        $this->actingAs($superadmin)
            ->patch("/admin/staff/{$superadmin->staffProfile->id}/status", ['status' => 'suspended'])
            ->assertStatus(422);
    }

    public function test_suspended_admin_account_is_blocked(): void
    {
        $admin = $this->adminUser();
        $admin->update(['status' => 'suspended']);

        $this->actingAs($admin)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_settings_update_is_validated_and_audited(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->patch('/admin/settings', [
                'default_facility_id' => $this->facility->id,
                'locale' => 'en',
                'timezone' => 'Africa/Lagos',
                'currency' => 'NGN',
                'date_format' => 'd/m/Y',
                'time_format' => 'H:i',
                'branding' => ['primary_color' => '#991b1b'],
                'contact_details' => ['email' => 'info@example.test'],
                'operating_preferences' => [],
                'public_site_defaults' => [],
                'numbering_preferences' => [],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_events', ['action' => 'settings.updated']);
    }

    public function test_number_sequence_allocation_is_unique_and_audited(): void
    {
        $sequence = NumberSequence::factory()->create([
            'hospital_id' => $this->hospital->id,
            'key' => 'patient_number',
            'prefix' => 'PAT',
            'date_format' => 'Y',
            'next_value' => 1,
        ]);

        $service = app(NumberSequenceService::class);

        $first = $service->allocate($sequence);
        $second = $service->allocate($sequence->fresh());

        $this->assertNotSame($first, $second);
        $this->assertSame(3, $sequence->fresh()->next_value);
        $this->assertDatabaseHas('audit_events', ['action' => 'number_sequences.allocated']);
    }

    public function test_audit_service_redacts_sensitive_fields(): void
    {
        app(AuditService::class)->record('security.test', metadata: [
            'password' => 'secret',
            'nested' => ['token' => 'abc'],
        ], hospital: $this->hospital);

        $event = AuditEvent::firstOrFail();

        $this->assertSame('[REDACTED]', $event->metadata['password']);
        $this->assertSame('[REDACTED]', $event->metadata['nested']['token']);
    }

    public function test_admin_pages_render_with_inertia(): void
    {
        $admin = $this->adminUser();

        foreach ([
            '/admin/hospital' => 'Admin/Hospital/Edit',
            '/admin/facilities' => 'Admin/Facilities/Index',
            '/admin/departments' => 'Admin/Departments/Index',
            '/admin/staff' => 'Admin/Staff/Index',
            '/admin/roles' => 'Admin/Roles',
            '/admin/audit-logs' => 'Admin/Audit/Index',
            '/admin/settings' => 'Admin/Settings/Edit',
            '/admin/numbering' => 'Admin/Numbering/Index',
        ] as $uri => $component) {
            $this->actingAs($admin)
                ->get($uri)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component($component));
        }
    }

    private function adminUser(string $role = 'admin'): User
    {
        $user = User::factory()->create(['access_level' => $role === 'admin' ? 'admin' : 'superadmin']);
        $user->syncRoles([$role]);

        $staff = StaffProfile::factory()->create([
            'user_id' => $user->id,
            'hospital_id' => $this->hospital->id,
        ]);

        FacilityMembership::create([
            'staff_profile_id' => $staff->id,
            'facility_id' => $this->facility->id,
            'is_default' => true,
            'status' => 'active',
        ]);

        return $user->load('staffProfile.memberships');
    }
}
