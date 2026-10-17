<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\TestCase;

class ClientNeutralBrandingTest extends TestCase
{
    public function test_application_ui_and_configuration_do_not_ship_client_specific_branding(): void
    {
        $forbidden = ['test'.'imony', 'kiet'.'mail'];
        $directories = ['app', 'config', 'resources', 'routes', 'database/seeders'];

        foreach ($directories as $directory) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($directory), \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (! $file->isFile() || ! in_array(strtolower($file->getExtension()), ['php', 'vue', 'js', 'html', 'json', 'css'], true)) {
                    continue;
                }

                $content = file_get_contents($file->getPathname());
                foreach ($forbidden as $term) {
                    $this->assertStringNotContainsStringIgnoringCase(
                        $term, $content, 'Client branding leaked into '.$file->getPathname()
                    );
                }
            }
        }
    }
}
