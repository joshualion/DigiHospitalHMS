<?php

namespace Tests\Feature;

use App\Support\MaintenancePage;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MaintenanceModePageTest extends TestCase
{
    protected function tearDown(): void
    {
        Artisan::call('up');

        parent::tearDown();
    }

    public function test_maintenance_page_data_builds_expected_contact_links_and_handles_expired_launch(): void
    {
        config()->set('maintenance_page.launch_at', '2025-01-01 00:00:00');
        config()->set('maintenance_page.timezone', 'Africa/Lagos');
        config()->set('maintenance_page.contact', [
            'phone' => '+234 800 111 2222',
            'whatsapp' => '+234 800 333 4444',
            'email' => 'care@testimony.example',
            'directions_url' => 'https://maps.example.test/testimony',
        ]);

        $page = app(MaintenancePage::class)->data();

        $this->assertSame('2025-01-01T00:00:00+01:00', $page['launch']['iso']);
        $this->assertSame('Email', $page['info'][2]['title']);
        $this->assertSame('care@testimony.example', $page['info'][2]['text']);
        $this->assertSame('tel:+2348001112222', $page['actions'][0]['href']);
        $this->assertSame('https://wa.me/2348003334444', $page['actions'][1]['href']);
        $this->assertSame('mailto:care@testimony.example', $page['actions'][2]['href']);
        $this->assertSame('https://maps.example.test/testimony', $page['actions'][3]['href']);
    }

    public function test_maintenance_page_view_renders_and_optional_contacts_are_safe(): void
    {
        config()->set('maintenance_page.launch_at', null);
        config()->set('maintenance_page.contact', [
            'phone' => null,
            'whatsapp' => null,
            'email' => 'hello@testimony.example',
            'directions_url' => null,
        ]);

        $html = view('errors.503')->render();

        $this->assertStringContainsString('Countdown to launch', $html);
        $this->assertStringContainsString('mailto:hello@testimony.example', $html);
        $this->assertStringContainsString('>Email<', $html);
        $this->assertStringNotContainsString('>Appointments<', $html);
        $this->assertStringNotContainsString('tel:', $html);
        $this->assertStringContainsString('Launching shortly.', $html);
    }

    public function test_laravel_maintenance_mode_returns_custom_503_page_and_recovers_after_up(): void
    {
        config()->set('maintenance_page.launch_at', '2030-03-04 09:15:00');
        config()->set('maintenance_page.contact.email', 'hello@testimony.example');

        Artisan::call('down', [
            '--render' => 'errors::503',
            '--secret' => 'preview-secret',
        ]);

        $this->get('/')
            ->assertStatus(503)
            ->assertSee('Website Update in Progress')
            ->assertDontSee('preview-secret', false);

        Artisan::call('up');

        $this->assertFalse(app()->isDownForMaintenance());
    }
}
