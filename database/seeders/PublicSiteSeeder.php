<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Hospital;
use App\Models\PublicSiteItem;
use App\Models\PublicSitePage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PublicSiteSeeder extends Seeder
{
    public function run(): void
    {
        $hospital = Hospital::primary() ?? Hospital::firstOrCreate(
            ['legal_name' => 'Demo Hospital'],
            ['display_name' => 'Demo Hospital', 'country' => 'Nigeria', 'timezone' => 'Africa/Lagos', 'default_currency' => 'NGN'],
        );

        foreach ($this->pages() as $pageData) {
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

        foreach ($this->items($hospital) as $item) {
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

    private function pages(): array
    {
        return [
            [
                'slug' => 'home',
                'title' => 'Home',
                'template' => 'home',
                'content' => [
                    'utility' => ['visible' => true, 'phone' => '+234 809 157 4444', 'emergency_phone' => '+234 809 157 4444', 'email' => 'info@example.test', 'hours' => 'Mon - Fri, 8:00 AM - 6:00 PM', 'location' => 'Lagos, Nigeria'],
                    'navigation' => ['appointment_label' => 'Appointment information', 'appointment_url' => '/appointment', 'items' => [['label' => 'Home', 'url' => '/'], ['label' => 'About', 'url' => '/about'], ['label' => 'Services', 'url' => '/services'], ['label' => 'Departments', 'url' => '/departments'], ['label' => 'Doctors', 'url' => '/doctors'], ['label' => 'News', 'url' => '/news'], ['label' => 'Contact', 'url' => '/contact']]],
                    'theme' => ['appearance' => 'system', 'accent' => 'calm', 'allowed_accents' => ['calm', 'healing', 'alert', 'blood', 'seagrass'], 'show_switcher' => true],
                    'footer' => ['summary' => 'A modern hospital website foundation. Replace placeholder copy with approved hospital content before production.', 'badges' => ['Managed publishing', 'Secure media controls', 'Accessible themes'], 'copyright' => 'Copyright {year} Demo Hospital. All rights reserved.'],
                ],
                'seo' => ['title' => 'Demo Hospital', 'description' => 'Modern hospital care information and contact details.', 'canonical_url' => '/'],
                'sections' => [
                    ['key' => 'hero', 'type' => 'hero_slider', 'label' => 'Hero slider', 'sort_order' => 10, 'content' => ['rotation_ms' => 6500, 'slides' => [
                        ['label' => 'Hospital care', 'headline' => 'Calm, coordinated care for every visit.', 'text' => 'A polished public website foundation for hospital information, access, and trust-building.', 'image' => '/frontend/images/slider/1.png', 'alt' => 'Hospital reception and care environment', 'primary_label' => 'Explore services', 'primary_url' => '/services', 'secondary_label' => 'Contact us', 'secondary_url' => '/contact', 'overlay' => 55],
                        ['label' => 'Specialist teams', 'headline' => 'Meet the professionals behind your care.', 'text' => 'Featured clinician profiles are managed safely from real staff records when approved for publication.', 'image' => '/frontend/images/slider/2.jpg', 'alt' => 'Healthcare professionals in consultation', 'primary_label' => 'View doctors', 'primary_url' => '/doctors', 'secondary_label' => 'Departments', 'secondary_url' => '/departments', 'overlay' => 50],
                    ]]],
                    ['key' => 'info_banner', 'type' => 'info_banner', 'label' => 'Information banner', 'sort_order' => 20, 'content' => ['items' => [['icon' => 'phone', 'heading' => 'Emergency contact', 'text' => '+234 809 157 4444', 'link_label' => 'Call now', 'url' => 'tel:+2348091574444'], ['icon' => 'clock', 'heading' => 'Opening hours', 'text' => 'Mon - Fri, 8:00 AM - 6:00 PM', 'link_label' => 'Plan your visit', 'url' => '/contact'], ['icon' => 'calendar', 'heading' => 'Appointment', 'text' => 'Visit guidance and appointment information.', 'link_label' => 'Book appointment', 'url' => '/appointment'], ['icon' => 'map-pin', 'heading' => 'Location', 'text' => 'Lagos, Nigeria', 'link_label' => 'Get directions', 'url' => '/contact']]]],
                    ['key' => 'about', 'type' => 'about', 'label' => 'About', 'sort_order' => 30, 'content' => ['label' => 'About the hospital', 'heading' => 'Designed around clear information and trusted access.', 'description' => 'This section is ready for approved hospital history, values, facilities, and patient information. Replace placeholder copy before production.', 'image' => '/frontend/images/slider/3.png', 'image_alt' => 'Modern hospital facility', 'points' => ['Clear public information', 'Managed publishing workflow', 'Section-by-section administration'], 'cta_label' => 'Our story', 'cta_url' => '/about']],
                    ['key' => 'services', 'type' => 'services', 'label' => 'Services', 'sort_order' => 40, 'content' => ['heading' => 'Hospital services', 'description' => 'Marketing service entries are informational and not billing catalogue records.']],
                    ['key' => 'departments', 'type' => 'departments', 'label' => 'Departments', 'sort_order' => 50, 'content' => ['heading' => 'Departments', 'description' => 'Public department profiles reference Phase 1A department records where available.']],
                    ['key' => 'why_choose_us', 'type' => 'trust', 'label' => 'Why choose us', 'sort_order' => 60, 'content' => ['heading' => 'Built for confident patient communication', 'items' => [['icon' => 'shield-check', 'heading' => 'Clear governance', 'text' => 'Drafts, reviews, publishing, and rollback are controlled.'], ['icon' => 'users', 'heading' => 'Team visibility', 'text' => 'Clinician profiles publish only approved public details.'], ['icon' => 'activity', 'heading' => 'Modern digital presence', 'text' => 'Fast, responsive, and structured for future service integrations.']]]],
                    ['key' => 'doctors', 'type' => 'clinicians', 'label' => 'Featured clinicians', 'sort_order' => 70, 'content' => ['heading' => 'Featured clinicians', 'description' => 'Profiles appear only when explicitly enabled for public display.']],
                    ['key' => 'testimonials', 'type' => 'testimonials', 'label' => 'Testimonials', 'sort_order' => 80, 'content' => ['heading' => 'Approved testimonials', 'description' => 'Placeholder testimonials must be replaced with approved, consented statements before production.']],
                    ['key' => 'appointment_cta', 'type' => 'cta', 'label' => 'Appointment CTA', 'sort_order' => 90, 'content' => ['heading' => 'Need appointment information?', 'text' => 'Online booking is not active yet. Contact the hospital for current visit information.', 'button_label' => 'Appointment information', 'button_url' => '/appointment', 'secondary_text' => 'For urgent needs, use the emergency contact number.']],
                    ['key' => 'news', 'type' => 'news', 'label' => 'News preview', 'sort_order' => 100, 'content' => ['heading' => 'News and updates', 'description' => 'Publish approved public news items here.']],
                    ['key' => 'contact', 'type' => 'contact', 'label' => 'Contact', 'sort_order' => 110, 'content' => ['heading' => 'Contact and location', 'address' => 'Configure hospital address', 'phone' => '+234 809 157 4444', 'email' => 'info@example.test', 'hours' => 'Mon - Fri, 8:00 AM - 6:00 PM', 'directions_url' => '/contact']],
                ],
            ],
            ['slug' => 'about', 'title' => 'About', 'content' => ['heading' => 'About the hospital', 'body' => 'Replace this placeholder with approved hospital information.'], 'seo' => ['title' => 'About Demo Hospital', 'description' => 'Learn about the hospital.']],
            ['slug' => 'services', 'title' => 'Services', 'content' => ['heading' => 'Services', 'body' => 'Marketing services are informational only.'], 'seo' => ['title' => 'Services', 'description' => 'Hospital service information.']],
            ['slug' => 'departments', 'title' => 'Departments', 'content' => ['heading' => 'Departments', 'body' => 'Public department information.'], 'seo' => ['title' => 'Departments', 'description' => 'Hospital departments.']],
            ['slug' => 'doctors', 'title' => 'Doctors', 'content' => ['heading' => 'Doctors', 'body' => 'Public clinician profiles.'], 'seo' => ['title' => 'Doctors', 'description' => 'Featured clinicians.']],
            ['slug' => 'news', 'title' => 'News', 'content' => ['heading' => 'News', 'body' => 'Approved hospital news.'], 'seo' => ['title' => 'News', 'description' => 'Hospital news and updates.']],
            ['slug' => 'contact', 'title' => 'Contact', 'content' => ['heading' => 'Contact', 'body' => 'Contact and location information.'], 'seo' => ['title' => 'Contact', 'description' => 'Contact the hospital.']],
            ['slug' => 'appointment', 'title' => 'Appointment Information', 'content' => ['heading' => 'Appointment information', 'body' => 'Online appointment booking is not active yet. Please contact the hospital for current visit guidance.'], 'seo' => ['title' => 'Appointment information', 'description' => 'Appointment information.']],
            ['slug' => 'policies', 'title' => 'Policies', 'content' => ['heading' => 'Policies', 'body' => 'Publish approved privacy and operational policy information here.'], 'seo' => ['title' => 'Policies', 'description' => 'Hospital policies.']],
        ];
    }

    private function items(Hospital $hospital): array
    {
        $departments = Department::where('hospital_id', $hospital->id)->orderBy('display_order')->take(3)->get();
        $departmentItems = $departments->map(fn (Department $department, int $index): array => [
            'section_key' => 'departments',
            'type' => 'department',
            'slug' => Str::slug($department->code ?: $department->name),
            'title' => $department->name,
            'summary' => 'Public summary for this department. Replace with approved copy before production.',
            'content' => ['icon' => 'building-2', 'public_title' => $department->name, 'summary' => 'Public summary for this department. Replace with approved copy before production.'],
            'presentable_type' => Department::class,
            'presentable_id' => $department->id,
            'sort_order' => $index + 1,
        ])->all();

        return array_merge([
            ['section_key' => 'services', 'type' => 'service', 'slug' => 'general-consultation', 'title' => 'General consultation', 'summary' => 'General outpatient information for visitors.', 'content' => ['icon' => 'stethoscope', 'description' => 'Informational service profile. Connect to operational service catalogue in a later billing phase only through explicit mapping.', 'cta_label' => 'Learn more', 'cta_url' => '/services'], 'sort_order' => 1],
            ['section_key' => 'services', 'type' => 'service', 'slug' => 'emergency-information', 'title' => 'Emergency information', 'summary' => 'How to contact the hospital for urgent care information.', 'content' => ['icon' => 'siren', 'description' => 'Use approved emergency contact and visit guidance. Do not make unverified 24/7 claims.', 'cta_label' => 'Contact', 'cta_url' => '/contact'], 'sort_order' => 2],
            ['section_key' => 'doctors', 'type' => 'clinician', 'slug' => 'sample-clinician-profile', 'title' => 'Sample clinician profile', 'summary' => 'Placeholder profile for layout verification only.', 'content' => ['display_name' => 'Sample clinician profile', 'professional_title' => 'Clinician profile placeholder', 'specialty' => 'Replace before production', 'bio' => 'This is demonstration content and must be replaced with an approved staff-linked profile before production.', 'photo' => '/frontend/images/doctors/prof.jpg', 'alt' => 'Placeholder clinician profile image'], 'sort_order' => 1],
            ['section_key' => 'testimonials', 'type' => 'testimonial', 'slug' => 'placeholder-testimonial', 'title' => 'Placeholder testimonial', 'summary' => 'Demonstration only. Replace with approved testimonial before production.', 'content' => ['display_name' => 'Approved testimonial placeholder', 'context' => 'Demonstration content', 'text' => 'This placeholder shows where approved testimonial content will appear. It is not a real patient statement.', 'rating' => null, 'approved' => true], 'sort_order' => 1],
            ['section_key' => 'news', 'type' => 'article', 'slug' => 'public-site-launch-note', 'title' => 'Public website publishing is ready for approved content', 'summary' => 'A placeholder news item explaining the website publishing workflow.', 'content' => ['excerpt' => 'This placeholder item can be replaced with approved hospital news.', 'body' => 'Use this section for approved public news and announcements. Avoid publishing clinical advice without review.', 'author' => 'Hospital communications', 'published_on' => now()->toDateString()], 'sort_order' => 1],
        ], $departmentItems);
    }
}
