<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\Patient;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3SecurityBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_unassigned_admin_cannot_inherit_primary_hospital_data(): void
    {
        $hospital = Hospital::factory()->create();
        $admin = User::factory()->create(['access_level' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)->get('/admin/facilities')->assertForbidden();
        $this->assertDatabaseCount('hospitals', 1);
    }

    public function test_unassigned_admin_cannot_create_demo_hospital_implicitly(): void
    {
        $admin = User::factory()->create(['access_level' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)->get('/admin/facilities')->assertForbidden();
        $this->assertDatabaseCount('hospitals', 0);
    }

    public function test_patient_policy_denies_other_hospitals_patient(): void
    {
        $hospitalA = Hospital::factory()->create();
        $hospitalB = Hospital::factory()->create();
        $user = User::factory()->create(['access_level' => 'admin']);
        $user->assignRole('admin');
        StaffProfile::factory()->create(['user_id' => $user->id, 'hospital_id' => $hospitalA->id]);
        $patient = new Patient(['hospital_id' => $hospitalB->id]);

        $this->assertFalse($user->can('view', $patient));
    }

    public function test_audit_service_redacts_nested_credentials(): void
    {
        $redacted = app(\\App\\Services\\AuditService::class)->redact([
            'password' => 'pass',
            'access_token' => 'token',
            'nested' => ['client_secret' => 'secret', 'recovery_codes' => ['abc', 'def']],
            'action' => 'staff_updated',
        ]);

        $this->assertSame('[REDACTED]', $redacted['password']);
        $this->assertSame('[REDACTED]', $redacted['access_token']);
        $this->assertSame('[REDACTED]', $redacted['nested']['client_secret']);
        $this->assertSame('[REDACTED]', $redacted['nested']['recovery_codes']);
        $this->assertSame('staff_updated', $redacted['action']);
    }

    public function test_authenticated_hospital_pages_prevent_caching(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/profile')
            ->assertOk()->assertHeader('Cache-Control', 'private, no-store, max-age=0');
    }
}
