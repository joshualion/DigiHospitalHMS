<?php

namespace Tests\Feature;

use Database\Seeders\HospitalFoundationSeeder;
use Database\Seeders\PublicSiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $this->seed(HospitalFoundationSeeder::class);
        $this->seed(PublicSiteSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Public/WebsitePage'));
    }
}
