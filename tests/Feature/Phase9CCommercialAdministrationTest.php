<?php

namespace Tests\Feature;

use App\Models\BackupMonitorEvent;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\IntegrationConfiguration;
use App\Models\InstallationLicense;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase9CCommercialAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_hospital_admin_can_manage_commercial_integration_and_backup_metadata(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $hospital=Hospital::factory()->create();
        Facility::factory()->create(['hospital_id'=>$hospital->id,'status'=>'active']);
        $user=User::factory()->create(['access_level'=>'admin']);
        $user->syncRoles(['hospital-admin']);
        StaffProfile::factory()->create([
            'user_id'=>$user->id,'hospital_id'=>$hospital->id,'staff_category'=>'administrative',
            'is_active'=>true,'employment_status'=>'active',
        ]);

        $this->assertTrue($user->can('commercial-admin.view'));
        $this->assertTrue($user->can('commercial-admin.manage'));
        $this->assertTrue($user->can('integrations.manage'));
        $this->assertTrue($user->can('backup-monitor.manage'));

        config(['inertia.testing.ensure_pages_exist'=>false]);
        $this->actingAs($user)->get('/admin/commercial')->assertOk()
            ->assertInertia(fn($page)=>$page->component('Admin/Commercial/Index')->has('license')->has('integrations')->has('backupEvents'));

        $this->actingAs($user)->patch('/admin/commercial/license',[
            'license_key'=>'LIC-TEST','plan'=>'professional','status'=>'active',
            'starts_on'=>today()->toDateString(),'expires_on'=>today()->addYear()->toDateString(),
            'licensed_facilities'=>3,'notes'=>'Test licence.',
        ])->assertRedirect();

        $integration=IntegrationConfiguration::where('hospital_id',$hospital->id)->where('type','payment')->firstOrFail();
        $this->actingAs($user)->patch("/admin/commercial/integrations/{$integration->id}",[
            'provider'=>'Example Gateway','status'=>'configured',
            'endpoint'=>'https://gateway.example.test','account_reference'=>'merchant-1','notes'=>'Credentials live in env.',
        ])->assertRedirect();

        $this->actingAs($user)->post('/admin/commercial/backups',[
            'status'=>'success','source'=>'ci','backup_reference'=>'backup-001',
            'size_bytes'=>2048,'backup_completed_at'=>now()->toDateTimeString(),
            'restore_verified_at'=>now()->toDateTimeString(),'notes'=>'Restore drill passed.',
        ])->assertRedirect();

        $this->assertDatabaseHas('installation_licenses',['hospital_id'=>$hospital->id,'plan'=>'professional','status'=>'active']);
        $this->assertDatabaseHas('integration_configurations',['hospital_id'=>$hospital->id,'type'=>'payment','provider'=>'Example Gateway','status'=>'configured']);
        $this->assertDatabaseHas('backup_monitor_events',['hospital_id'=>$hospital->id,'status'=>'success','backup_reference'=>'backup-001']);
        $this->assertDatabaseHas('audit_events',['action'=>'backup.monitor_event_recorded']);
    }
}
