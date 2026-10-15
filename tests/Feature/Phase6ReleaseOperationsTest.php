<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class Phase6ReleaseOperationsTest extends TestCase
{
    public function test_release_version_reports_explicit_version_and_revision(): void
    {
        config(['release.version' => '6.0.0-rc1', 'release.revision' => str_repeat('a', 40)]);
        $result = Artisan::call('hms:version', ['--json' => true]);
        $this->assertSame(0, $result);
        $json = json_decode(Artisan::output(), true);
        $this->assertSame('DigiHospitalHMS', $json['application']);
        $this->assertSame('6.0.0-rc1', $json['version']);
        $this->assertSame(str_repeat('a', 40), $json['revision']);
    }

    public function test_production_preflight_fails_closed_when_security_configuration_is_insecure(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => true,
            'app.url' => 'http://hospital.test',
            'session.secure' => false,
            'session.encrypt' => false,
        ]);
        $exit = Artisan::call('hms:preflight', ['--production' => true, '--json' => true]);
        $this->assertSame(1, $exit);
        $json = json_decode(Artisan::output(), true);
        $this->assertFalse($json['ok']);
        $failed = array_column(array_filter($json['checks'], fn ($row) => ! $row['passed']), 'check');
        $this->assertContains('debug', $failed);
        $this->assertContains('https-url', $failed);
        $this->assertContains('secure-cookie', $failed);
        $this->assertContains('session-encryption', $failed);
    }

    public function test_deployment_script_requires_reviewed_backup_evidence_and_revision(): void
    {
        $script = file_get_contents(base_path('scripts/deploy-release.sh'));
        $this->assertStringContainsString('--backup-evidence', $script);
        $this->assertStringContainsString('--revision', $script);
        $this->assertStringContainsString('git merge --ff-only', $script);
        $this->assertStringContainsString('php artisan down', $script);
        $this->assertStringContainsString('php artisan migrate --force', $script);
        $this->assertStringContainsString('php artisan up', $script);
    }
}
