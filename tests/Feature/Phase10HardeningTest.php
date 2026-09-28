<?php

namespace Tests\Feature;

use Tests\TestCase;

class Phase10HardeningTest extends TestCase
{
    public function test_inertia_page_discovery_uses_case_correct_vue_pages_directory(): void
    {
        $this->assertContains(resource_path('js/Pages'), config('inertia.pages.paths'));
        $this->assertTrue((bool) config('inertia.testing.ensure_pages_exist'));
    }

    public function test_web_responses_include_baseline_security_headers(): void
    {
        $response=$this->get('/login');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options','nosniff');
        $response->assertHeader('X-Frame-Options','DENY');
        $response->assertHeader('Referrer-Policy','strict-origin-when-cross-origin');
        $response->assertHeader('Cross-Origin-Opener-Policy','same-origin');
        $response->assertHeader('Cross-Origin-Resource-Policy','same-origin');
    }
}
