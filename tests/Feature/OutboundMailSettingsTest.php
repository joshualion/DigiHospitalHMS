<?php

namespace Tests\Feature;

use App\Models\OutboundMailSetting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OutboundMailSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function payload(): array
    {
        return [
            'enabled' => true,
            'provider' => 'custom',
            'host' => 'mail.testimonyhealthcare.com',
            'port' => 2525,
            'encryption' => 'tls',
            'username' => 'hospital@example.test',
            'password' => 'example-app-password',
            'from_address' => 'hospital@example.test',
            'from_name' => 'Test Hospital',
        ];
    }

    public function test_only_superadmin_can_change_outbound_email_settings(): void
    {
        $other = User::factory()->create();
        $other->syncRoles(['patient']);
        $this->actingAs($other)->patch('/admin/settings/outbound-mail', $this->payload())->assertForbidden();

        $superadmin = User::factory()->create();
        $superadmin->syncRoles(['superadmin']);
        $this->actingAs($superadmin)->patch('/admin/settings/outbound-mail', $this->payload())
            ->assertRedirect()->assertSessionHasNoErrors();

        $setting = OutboundMailSetting::firstOrFail();
        $this->assertSame('mail.testimonyhealthcare.com', $setting->host);
        $this->assertSame(2525, $setting->port);
        $this->assertSame('example-app-password', $setting->password);
        $this->assertStringNotContainsString('example-app-password', $setting->getRawOriginal('password'));
    }

    public function test_smtp_examples_use_active_request_host(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['superadmin']);

        $this->actingAs($admin)->withServerVariables(['HTTP_HOST' => 'hospital.example.org'])
            ->get('/admin/settings/outbound-mail')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Settings/OutboundMail')
                ->where('suggestedDomain', 'hospital.example.org'));

        $this->actingAs($admin)->withServerVariables(['HTTP_HOST' => 'localhost'])
            ->get('/admin/settings/outbound-mail')
            ->assertInertia(fn ($page) => $page->where('suggestedDomain', null));
    }

    public function test_superadmin_can_test_mail_transport_without_exposing_secret(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['superadmin']);
        Mail::fake();

        $this->actingAs($admin)->get('/admin/settings/outbound-mail')->assertOk()
            ->assertDontSee('example-app-password');
        $this->actingAs($admin)->post('/admin/settings/outbound-mail/test', [
            'recipient' => 'recipient@example.test',
        ])->assertRedirect()->assertSessionHasNoErrors();
    }
}
