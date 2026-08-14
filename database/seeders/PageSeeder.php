<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $page = Page::updateOrCreate(
            ['slug' => 'home'],
            [
                'title' => 'Home',
                'meta_title' => 'Welcome to HMS',
                'meta_description' => 'Healthcare management system homepage',
            ],
        );

        $page->sections()->updateOrCreate(
            ['key' => 'hero'],
            [
                'title' => 'Hero Slider',
                'content' => [
                    ['image' => 'frontend/images/slider/1.png', 'title' => 'Your Health, Our Priority', 'subtitle' => 'Providing world-class healthcare services with compassion and care.'],
                    ['image' => 'frontend/images/slider/2.jpg', 'title' => 'Expert Doctors', 'subtitle' => 'Our team of specialists ensures you get the best care possible.'],
                    ['image' => 'frontend/images/slider/3.png', 'title' => 'Advanced Facilities', 'subtitle' => 'Equipped with modern technology for accurate diagnosis and treatment.'],
                ],
                'sort_order' => 0,
            ],
        );

        $services = $page->sections()->updateOrCreate(
            ['key' => 'services'],
            [
                'title' => 'Our Services',
                'sort_order' => 1,
            ],
        );

        foreach ([
            ['General Consultation', 'Comprehensive medical consultation for all ages.'],
            ['Pediatrics', 'Expert care for infants, children, and adolescents.'],
            ['Surgery', 'State-of-the-art surgical procedures with top surgeons.'],
        ] as $i => $srv) {
            $services->blocks()->updateOrCreate(
                ['sort_order' => $i],
                [
                    'title' => $srv[0],
                    'description' => $srv[1],
                ],
            );
        }
    }
}
