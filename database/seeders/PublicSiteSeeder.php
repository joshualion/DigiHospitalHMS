<?php

namespace Database\Seeders;

use App\Models\Hospital;
use App\Models\PublicSiteItem;
use App\Models\PublicSitePage;
use Illuminate\Database\Seeder;

class PublicSiteSeeder extends Seeder
{
    public function run(): void
    {
        $hospital = Hospital::primary() ?? Hospital::firstOrCreate(
            ['legal_name' => 'Hospital'],
            ['display_name' => 'Hospital', 'timezone' => config('app.timezone'), 'default_currency' => 'NGN'],
        );

        foreach ($this->pages($hospital) as $pageData) {
            $page = PublicSitePage::updateOrCreate(
                ['hospital_id' => $hospital->id, 'slug' => $pageData['slug']],
                [
                    'title' => $pageData['title'],
                    'draft_title' => $pageData['title'],
                    'published_title' => $pageData['title'],
                    'template' => $pageData['template'] ?? 'standard',
                    'status' => 'published',
                    'draft_content' => $pageData['content'],
                    'published_content' => $pageData['content'],
                    'seo' => $pageData['seo'],
                    'draft_seo' => $pageData['seo'],
                    'published_seo' => $pageData['seo'],
                    'published_version' => 1,
                    'published_at' => now(),
                ],
            );

            foreach ($pageData['sections'] ?? [] as $sectionData) {
                $page->sections()->updateOrCreate(
                    ['key' => $sectionData['key']],
                    [
                        'type' => $sectionData['type'],
                        'label' => $sectionData['label'],
                        'draft_label' => $sectionData['label'],
                        'published_label' => $sectionData['label'],
                        'sort_order' => $sectionData['sort_order'],
                        'draft_sort_order' => $sectionData['sort_order'],
                        'published_sort_order' => $sectionData['sort_order'],
                        'is_enabled' => $sectionData['is_enabled'] ?? true,
                        'draft_is_enabled' => $sectionData['is_enabled'] ?? true,
                        'published_is_enabled' => $sectionData['is_enabled'] ?? true,
                        'draft_content' => $sectionData['content'],
                        'published_content' => $sectionData['content'],
                        'published_version' => 1,
                        'published_at' => now(),
                    ],
                );
            }
        }

        $home = PublicSitePage::where('hospital_id', $hospital->id)->where('slug', 'home')->firstOrFail();
        $sections = $home->sections()->get()->keyBy('key');

        foreach ($this->items() as $item) {
            PublicSiteItem::updateOrCreate(
                ['hospital_id' => $hospital->id, 'type' => $item['type'], 'slug' => $item['slug']],
                [
                    'public_site_section_id' => $sections[$item['section_key']]?->id ?? null,
                    'draft_public_site_section_id' => $sections[$item['section_key']]?->id ?? null,
                    'published_public_site_section_id' => $sections[$item['section_key']]?->id ?? null,
                    'presentable_type' => $item['presentable_type'] ?? null,
                    'presentable_id' => $item['presentable_id'] ?? null,
                    'title' => $item['title'],
                    'draft_title' => $item['title'],
                    'published_title' => $item['title'],
                    'summary' => $item['summary'] ?? null,
                    'draft_summary' => $item['summary'] ?? null,
                    'published_summary' => $item['summary'] ?? null,
                    'draft_type' => $item['type'],
                    'published_type' => $item['type'],
                    'draft_slug' => $item['slug'],
                    'published_slug' => $item['slug'],
                    'draft_content' => $item['content'],
                    'published_content' => $item['content'],
                    'status' => 'published',
                    'is_enabled' => $item['is_enabled'] ?? true,
                    'draft_is_enabled' => $item['is_enabled'] ?? true,
                    'published_is_enabled' => $item['is_enabled'] ?? true,
                    'is_featured' => $item['is_featured'] ?? true,
                    'draft_is_featured' => $item['is_featured'] ?? true,
                    'published_is_featured' => $item['is_featured'] ?? true,
                    'sort_order' => $item['sort_order'],
                    'draft_sort_order' => $item['sort_order'],
                    'published_sort_order' => $item['sort_order'],
                    'published_version' => 1,
                    'published_at' => now(),
                ],
            );
        }
    }

    private function pages(Hospital $hospital): array
    {
        $phone = $hospital->phone_numbers[0] ?? null;
        $address = trim(collect([$hospital->address, $hospital->city, $hospital->state, $hospital->country])->filter()->implode(', '));

        return [
            [
                'slug' => 'home',
                'title' => 'Home',
                'template' => 'home',
                'content' => [
                    'utility' => ['visible' => false, 'phone' => $phone, 'emergency_phone' => null, 'email' => $hospital->email, 'hours' => null, 'location' => $address ?: null],
                    'navigation' => ['appointment_label' => 'Appointment information', 'appointment_url' => '/appointment', 'items' => [['label' => 'Home', 'url' => '/'], ['label' => 'About', 'url' => '/about'], ['label' => 'Services', 'url' => '/services'], ['label' => 'Departments', 'url' => '/departments'], ['label' => 'Doctors', 'url' => '/doctors'], ['label' => 'News', 'url' => '/news'], ['label' => 'Contact', 'url' => '/contact']]],
                    'theme' => ['appearance' => 'system', 'accent' => 'calm', 'allowed_accents' => ['calm', 'healing', 'alert', 'blood', 'seagrass'], 'show_switcher' => true],
                    'footer' => ['summary' => '', 'badges' => [], 'copyright' => "Copyright {year} {$hospital->display_name}. All rights reserved."],
                ],
                'seo' => ['title' => $hospital->display_name, 'description' => '', 'canonical_url' => '/'],
                'sections' => [
                    ['key' => 'hero', 'type' => 'hero_slider', 'label' => 'Hero slider', 'sort_order' => 10, 'content' => ['rotation_ms' => 6500, 'slides' => []]],
                    ['key' => 'info_banner', 'type' => 'info_banner', 'label' => 'Information banner', 'sort_order' => 20, 'content' => ['items' => []]],
                    ['key' => 'about', 'type' => 'about', 'label' => 'About', 'sort_order' => 30, 'content' => []],
                    ['key' => 'services', 'type' => 'services', 'label' => 'Services', 'sort_order' => 40, 'content' => []],
                    ['key' => 'departments', 'type' => 'departments', 'label' => 'Departments', 'sort_order' => 50, 'content' => []],
                    ['key' => 'why_choose_us', 'type' => 'trust', 'label' => 'Why choose us', 'sort_order' => 60, 'content' => ['items' => []]],
                    ['key' => 'doctors', 'type' => 'clinicians', 'label' => 'Featured clinicians', 'sort_order' => 70, 'content' => []],
                    ['key' => 'testimonials', 'type' => 'testimonials', 'label' => 'Testimonials', 'sort_order' => 80, 'content' => []],
                    ['key' => 'appointment_cta', 'type' => 'cta', 'label' => 'Appointment CTA', 'sort_order' => 90, 'content' => []],
                    ['key' => 'news', 'type' => 'news', 'label' => 'News preview', 'sort_order' => 100, 'content' => []],
                    ['key' => 'contact', 'type' => 'contact', 'label' => 'Contact', 'sort_order' => 110, 'content' => []],
                ],
            ],
            ['slug' => 'about', 'title' => 'About', 'content' => [], 'seo' => ['title' => 'About', 'description' => '']],
            ['slug' => 'services', 'title' => 'Services', 'content' => [], 'seo' => ['title' => 'Services', 'description' => '']],
            ['slug' => 'departments', 'title' => 'Departments', 'content' => [], 'seo' => ['title' => 'Departments', 'description' => '']],
            ['slug' => 'doctors', 'title' => 'Doctors', 'content' => [], 'seo' => ['title' => 'Doctors', 'description' => '']],
            ['slug' => 'news', 'title' => 'News', 'content' => [], 'seo' => ['title' => 'News', 'description' => '']],
            ['slug' => 'contact', 'title' => 'Contact', 'content' => [], 'seo' => ['title' => 'Contact', 'description' => '']],
            ['slug' => 'appointment', 'title' => 'Appointment Information', 'content' => [], 'seo' => ['title' => 'Appointment information', 'description' => '']],
            ['slug' => 'policies', 'title' => 'Policies', 'content' => [], 'seo' => ['title' => 'Policies', 'description' => '']],
        ];
    }

    private function items(): array
    {
        return [];
    }
}
