<?php

namespace Tests\Feature;

use App\Models\BillableService;
use App\Models\BillableServiceCategory;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\PublicSiteItem;
use App\Models\PublicSiteMedia;
use App\Models\PublicSitePage;
use App\Models\PublicSiteRevision;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PublicSiteSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase1BPublicSiteTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->hospital = Hospital::factory()->create(['display_name' => 'Phase 1B Hospital']);
        $facility = Facility::factory()->create(['hospital_id' => $this->hospital->id, 'is_primary' => true]);
        HospitalSetting::create(['hospital_id' => $this->hospital->id, 'default_facility_id' => $facility->id]);
        Department::factory()->create([
            'hospital_id' => $this->hospital->id,
            'name' => 'Cardiology',
            'category' => 'clinical',
            'public_is_visible' => true,
            'public_is_featured' => true,
            'public_slug' => 'cardiology',
            'public_name' => 'Cardiology',
            'public_description' => 'Approved public department summary.',
            'public_display_order' => 1,
        ]);

        $this->seed(PublicSiteSeeder::class);
        $this->seedPublicItemFixtures();
    }

    public function test_published_public_homepage_renders_with_section_content(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/WebsitePage')
                ->where('page.slug', 'home')
                ->where('site.theme.appearance', 'system')
                ->where('site.theme.accent', 'calm')
                ->where('site.theme.switcherVisible', true)
                ->where('site.theme.allowedAccents', ['calm', 'healing', 'alert', 'blood', 'seagrass'])
                ->has('sections.hero')
                ->has('items.service', 2));
    }

    public function test_theme_defaults_are_draft_controlled_validated_audited_and_published(): void
    {
        $editor = $this->userWithPermissions(['website.view', 'website.edit', 'website.manage_theme']);
        $page = PublicSitePage::where('slug', 'home')->firstOrFail();

        $this->actingAs($editor)->patch("/admin/public-website/pages/{$page->id}/theme", [
            'appearance' => 'dark',
            'accent' => 'seagrass',
            'allowed_accents' => ['calm', 'seagrass'],
            'show_switcher' => false,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertSame('dark', $page->draft_content['theme']['appearance']);
        $this->assertSame('seagrass', $page->draft_content['theme']['accent']);
        $this->assertSame(['calm', 'seagrass'], $page->draft_content['theme']['allowed_accents']);
        $this->assertFalse($page->draft_content['theme']['show_switcher']);
        $this->assertDatabaseHas('audit_events', ['action' => 'website.theme_updated']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('site.theme.accent', 'calm')
                ->where('site.theme.switcherVisible', true));

        $publisher = $this->userWithPermissions(['website.view', 'website.edit', 'website.publish']);
        $this->actingAs($publisher)->post("/admin/public-website/pages/{$page->id}/publish")->assertRedirect();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('site.theme.appearance', 'dark')
                ->where('site.theme.accent', 'seagrass')
                ->where('site.theme.allowedAccents', ['calm', 'seagrass'])
                ->where('site.theme.switcherVisible', false));
    }

    public function test_theme_settings_require_permission_and_reject_uncontrolled_values(): void
    {
        $editor = $this->userWithPermissions(['website.view', 'website.edit']);
        $editor->syncRoles([]);
        $editor->syncPermissions(['website.view', 'website.edit']);
        $page = PublicSitePage::where('slug', 'home')->firstOrFail();

        $this->actingAs($editor)->patch("/admin/public-website/pages/{$page->id}/theme", [
            'appearance' => 'light',
            'accent' => 'healing',
            'allowed_accents' => ['healing'],
            'show_switcher' => true,
        ])->assertForbidden();

        $manager = $this->userWithPermissions(['website.view', 'website.edit', 'website.manage_theme']);
        $this->actingAs($manager)->patch("/admin/public-website/pages/{$page->id}/theme", [
            'appearance' => 'neon',
            'accent' => '#ff0',
            'allowed_accents' => ['calm', 'javascript:alert(1)'],
            'show_switcher' => true,
        ])->assertSessionHasErrors(['appearance', 'accent', 'allowed_accents.1']);
    }

    public function test_draft_page_content_is_hidden_publicly_but_visible_in_authorized_preview(): void
    {
        $page = PublicSitePage::where('slug', 'home')->firstOrFail();
        $page->update(['draft_content' => ['summary' => 'Private draft summary']]);

        $this->get('/')->assertOk()->assertDontSee('Private draft summary');
        $this->get(URL::temporarySignedRoute('public.preview', now()->addMinutes(10), ['page' => $page]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('mode', 'preview')->where('page.content.summary', 'Private draft summary'));
    }

    public function test_unsigned_preview_and_guest_admin_access_are_rejected(): void
    {
        $page = PublicSitePage::where('slug', 'home')->firstOrFail();

        $this->get("/preview/public-site/{$page->id}")->assertForbidden();
        $this->get('/admin/public-website')->assertRedirect('/login');
    }

    public function test_editor_can_save_draft_but_cannot_publish(): void
    {
        $editor = $this->userWithPermissions(['website.view', 'website.edit']);
        $page = PublicSitePage::where('slug', 'home')->firstOrFail();

        $this->actingAs($editor)->patch("/admin/public-website/pages/{$page->id}", [
            'title' => 'Draft Home',
            'draft_content' => ['summary' => 'Draft only'],
            'seo' => ['title' => 'Draft SEO'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Draft Home', $page->refresh()->draft_title);
        $this->assertNotSame('Draft Home', $page->published_title);
        $this->assertNull($page->published_content['summary'] ?? null);

        $this->actingAs($editor)->post("/admin/public-website/pages/{$page->id}/publish")->assertForbidden();
    }

    public function test_publisher_can_publish_unpublish_and_restore_revisions(): void
    {
        $publisher = $this->userWithPermissions(['website.view', 'website.edit', 'website.publish', 'website.unpublish', 'website.view_revisions', 'website.restore_revision']);
        $page = PublicSitePage::where('slug', 'home')->firstOrFail();
        $page->update(['draft_content' => ['summary' => 'Published summary']]);

        $this->actingAs($publisher)->post("/admin/public-website/pages/{$page->id}/publish")->assertRedirect();
        $this->assertSame('published', $page->refresh()->status);
        $this->assertSame('Published summary', $page->published_content['summary']);
        $this->assertDatabaseHas('public_site_revisions', ['revisionable_type' => PublicSitePage::class, 'revisionable_id' => $page->id, 'action' => 'publish']);
        $this->assertDatabaseHas('audit_events', ['action' => 'website.published']);

        $revision = PublicSiteRevision::where('revisionable_type', PublicSitePage::class)->where('revisionable_id', $page->id)->firstOrFail();
        $page->update(['draft_content' => ['summary' => 'Changed draft']]);
        $this->actingAs($publisher)->post("/admin/public-website/revisions/{$revision->id}/restore")->assertRedirect();
        $this->assertSame('Published summary', $page->refresh()->draft_content['summary']);

        $this->actingAs($publisher)->post("/admin/public-website/pages/{$page->id}/unpublish")->assertRedirect();
        $this->assertSame('draft', $page->refresh()->status);
    }

    public function test_public_visible_fields_remain_draft_until_publish(): void
    {
        $publisher = $this->userWithPermissions(['website.view', 'website.edit', 'website.publish']);
        $home = PublicSitePage::where('slug', 'home')->with('sections')->firstOrFail();
        $serviceSection = $home->sections->firstWhere('key', 'services');
        $service = BillableService::where('public_slug', 'general-consultation')->firstOrFail();

        $this->actingAs($publisher)->patch("/admin/public-website/pages/{$home->id}", [
            'title' => 'Draft-only home title',
            'draft_content' => array_merge($home->draft_content, ['footer' => ['summary' => 'Draft-only footer']]),
            'seo' => ['title' => 'Draft SEO', 'canonical_url' => '/draft-home'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($publisher)->patch("/admin/public-website/sections/{$serviceSection->id}", [
            'label' => 'Draft services label',
            'sort_order' => 999,
            'is_enabled' => false,
            'draft_content' => ['heading' => 'Draft-only services heading', 'description' => 'Draft-only service description'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $service->update(['public_name' => 'Draft-only service title']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('page.title', 'Home')
                ->where('page.seo.canonical_url', 'http://localhost')
                ->where('items.service.0.slug', 'general-consultation')
                ->where('items.service.0.title', 'Draft-only service title'));

        $this->actingAs($publisher)->post("/admin/public-website/pages/{$home->id}/publish")->assertRedirect();
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('page.title', 'Draft-only home title')
                ->where('page.seo.canonical_url', 'http://localhost/draft-home')
                ->missing('sections.services')
                ->has('items.service', 2)
                ->where('items.service.0.title', 'Draft-only service title'));
    }

    public function test_item_unpublish_removes_content_and_requires_permission(): void
    {
        $editor = $this->userWithPermissions(['website.view', 'website.edit']);
        $publisher = $this->userWithPermissions(['website.view', 'website.edit', 'website.publish', 'website.unpublish']);
        $item = PublicSiteItem::where('type', 'article')->firstOrFail();

        $this->actingAs($editor)->post("/admin/public-website/items/{$item->id}/unpublish")->assertForbidden();
        $this->actingAs($publisher)->post("/admin/public-website/items/{$item->id}/unpublish")->assertRedirect();

        $this->assertSame('draft', $item->refresh()->status);
        $this->assertDatabaseHas('public_site_revisions', [
            'revisionable_type' => PublicSiteItem::class,
            'revisionable_id' => $item->id,
            'action' => 'unpublish',
        ]);
        $this->assertDatabaseHas('audit_events', ['action' => 'website.unpublished']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('items.article'));
    }

    public function test_repeated_public_website_service_creation_generates_unique_slugs(): void
    {
        $editor = $this->userWithPermissions(['website.view', 'website.edit']);
        $servicesPage = PublicSitePage::where('slug', 'services')->with('sections')->firstOrFail();
        $section = $servicesPage->sections->firstWhere('key', 'services');
        $payload = [
            'public_site_section_id' => $section?->id,
            'type' => 'service',
            'slug' => '',
            'title' => 'New service',
            'summary' => '',
            'draft_content' => ['icon' => 'stethoscope', 'description' => null, 'cta_label' => 'Learn more', 'cta_url' => '/services'],
            'status' => 'draft',
            'is_enabled' => true,
            'is_featured' => true,
            'sort_order' => 1,
        ];

        $this->actingAs($editor)->post("/admin/public-website/pages/{$servicesPage->id}/items", $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($editor)->post("/admin/public-website/pages/{$servicesPage->id}/items", $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('public_site_items', ['hospital_id' => $this->hospital->id, 'type' => 'service', 'slug' => 'new-service']);
        $this->assertDatabaseHas('public_site_items', ['hospital_id' => $this->hospital->id, 'type' => 'service', 'slug' => 'new-service-2']);
    }

    public function test_published_cms_service_items_render_on_services_page(): void
    {
        $publisher = $this->userWithPermissions(['website.view', 'website.edit', 'website.publish']);
        $servicesPage = PublicSitePage::where('slug', 'services')->with('sections')->firstOrFail();
        $section = $servicesPage->sections->firstWhere('key', 'services');

        $item = PublicSiteItem::create([
            'hospital_id' => $this->hospital->id,
            'public_site_section_id' => null,
            'draft_public_site_section_id' => $section?->id,
            'type' => 'service',
            'draft_type' => 'service',
            'slug' => 'patient-education',
            'draft_slug' => 'patient-education',
            'title' => 'Patient education',
            'draft_title' => 'Patient education',
            'summary' => 'Classes and guidance for patients.',
            'draft_summary' => 'Classes and guidance for patients.',
            'draft_content' => ['icon' => 'stethoscope', 'description' => 'Classes and guidance for patients.', 'cta_label' => 'Learn more', 'cta_url' => '/services'],
            'status' => 'draft',
            'is_enabled' => false,
            'draft_is_enabled' => true,
            'is_featured' => false,
            'draft_is_featured' => true,
            'sort_order' => 3,
            'draft_sort_order' => 3,
        ]);

        $this->actingAs($publisher)->post("/admin/public-website/items/{$item->id}/publish")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->get('/services')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('items.service', 3)
            ->where('items.service.2.slug', 'patient-education')
            ->where('items.service.2.source', 'public_site_item'));
    }

    public function test_structured_repeaters_map_to_draft_payload_and_publish_in_order(): void
    {
        $publisher = $this->userWithPermissions(['website.view', 'website.edit', 'website.publish']);
        $home = PublicSitePage::where('slug', 'home')->with('sections')->firstOrFail();
        $hero = $home->sections->firstWhere('key', 'hero');
        $draft = $home->draft_content;
        $draft['footer']['badges'] = ['First badge', 'Second badge'];

        $this->actingAs($publisher)->patch("/admin/public-website/pages/{$home->id}", [
            'title' => $home->draft_title,
            'draft_content' => $draft,
            'seo' => ['title' => 'Structured page', 'canonical_url' => '/structured'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($publisher)->patch("/admin/public-website/sections/{$hero->id}", [
            'label' => 'Hero slider',
            'sort_order' => 10,
            'is_enabled' => true,
            'draft_content' => [
                'rotation_ms' => 5000,
                'slides' => [
                    ['label' => 'Second', 'headline' => 'Second slide', 'text' => 'Second body', 'image' => '/second.jpg', 'alt' => 'Second alt', 'active' => true],
                    ['label' => 'First', 'headline' => 'First slide', 'text' => 'First body', 'image' => '/first.jpg', 'alt' => 'First alt', 'active' => true],
                ],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Second slide', $hero->refresh()->draft_content['slides'][0]['headline']);
        $this->assertSame(['First badge', 'Second badge'], $home->refresh()->draft_content['footer']['badges']);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('sections.hero.content.slides', 0));

        $this->actingAs($publisher)->post("/admin/public-website/pages/{$home->id}/publish")->assertRedirect();

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('sections.hero.content.slides.0.headline', 'Second slide')
            ->where('site.footer.badges', ['First badge', 'Second badge'])
            ->where('page.seo.canonical_url', 'http://localhost/structured'));
    }

    public function test_detail_pages_render_published_item_content_and_media(): void
    {
        $clinician = StaffProfile::where('public_slug', 'published-clinician-fixture')->firstOrFail();
        $clinician->forceFill([
            'public_display_name' => 'Published Clinician',
            'public_slug' => 'published-clinician',
            'public_specialty' => 'Published clinician summary',
            'public_summary' => 'Published clinician biography',
            'public_photo_path' => '/clinician.jpg',
            'public_photo_alt' => 'Clinician portrait',
        ])->save();

        $article = PublicSiteItem::where('type', 'article')->firstOrFail();
        $article->forceFill([
            'published_title' => 'Published Article',
            'published_slug' => 'published-article',
            'published_summary' => 'Published article summary',
            'published_content' => ['body' => 'Published article body', 'image' => '/article.jpg', 'alt' => 'Article image'],
        ])->save();

        $this->get('/doctors/published-clinician')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('page.title', 'Published Clinician')
                ->where('page.content.bio', 'Published clinician biography')
                ->where('page.content.photo', '/clinician.jpg'));

        $this->get('/news/published-article')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('page.title', 'Published Article')
                ->where('page.content.body', 'Published article body')
                ->where('page.content.image', '/article.jpg'));
    }

    public function test_section_order_visibility_service_department_clinician_and_testimonial_rules(): void
    {
        $home = PublicSitePage::where('slug', 'home')->with('sections')->firstOrFail();
        $section = $home->sections->firstWhere('type', 'services');
        $service = BillableService::where('public_slug', 'general-consultation')->firstOrFail();
        $department = Department::where('public_slug', 'cardiology')->firstOrFail();
        $clinician = StaffProfile::where('public_slug', 'published-clinician-fixture')->firstOrFail();
        $testimonial = PublicSiteItem::where('type', 'testimonial')->firstOrFail();

        $this->assertTrue($section->is_enabled);
        $this->assertTrue($service->public_is_visible);
        $this->assertTrue($department->public_is_visible);
        $this->assertTrue($clinician->public_is_visible);
        $this->assertStringContainsString('approved', strtolower($testimonial->summary));

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('items.service', 2)
                ->where('items.service.0.slug', 'general-consultation')
                ->where('items.service.1.slug', 'emergency-information')
                ->where('items.clinician.0.source', 'staff_profile')
                ->where('items.clinician.0.title', 'Published Clinician Fixture')
                ->missing('items.clinician.0.email')
                ->missing('items.clinician.0.content.email')
                ->missing('items.service.0.code')
                ->missing('items.service.0.prices'));

        $service->update(['public_is_visible' => false]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('items.service', 1)
                ->where('items.service.0.slug', 'emergency-information'));
    }

    public function test_dynamic_public_records_respect_featured_private_active_and_listing_rules(): void
    {
        $category = BillableServiceCategory::where('hospital_id', $this->hospital->id)->firstOrFail();
        BillableService::create([
            'hospital_id' => $this->hospital->id,
            'billable_service_category_id' => $category->id,
            'code' => 'PRIVATE_SERVICE',
            'name' => 'Private service',
            'is_active' => true,
            'public_is_visible' => false,
            'public_is_featured' => true,
            'public_slug' => 'private-service',
        ]);
        BillableService::create([
            'hospital_id' => $this->hospital->id,
            'billable_service_category_id' => $category->id,
            'code' => 'PUBLIC_UNFEATURED',
            'name' => 'Public unfeatured service',
            'is_active' => true,
            'public_is_visible' => true,
            'public_is_featured' => false,
            'public_slug' => 'public-unfeatured-service',
            'public_description' => 'Public listing only service.',
            'public_display_order' => 99,
        ]);

        Department::factory()->create([
            'hospital_id' => $this->hospital->id,
            'name' => 'Private Department',
            'status' => 'active',
            'public_is_visible' => false,
            'public_is_featured' => true,
            'public_slug' => 'private-department',
        ]);
        Department::factory()->create([
            'hospital_id' => $this->hospital->id,
            'name' => 'Public Listing Department',
            'status' => 'active',
            'public_is_visible' => true,
            'public_is_featured' => false,
            'public_slug' => 'public-listing-department',
            'public_description' => 'Public listing only department.',
            'public_display_order' => 99,
        ]);

        $doctor = User::factory()->create(['firstname' => 'Listing', 'lastname' => 'Doctor', 'status' => 'active']);
        StaffProfile::factory()->create([
            'user_id' => $doctor->id,
            'hospital_id' => $this->hospital->id,
            'staff_category' => 'clinical',
            'job_title' => 'Doctor',
            'public_is_visible' => true,
            'public_is_featured' => false,
            'public_slug' => 'listing-doctor',
            'public_display_name' => 'Listing Doctor',
            'public_display_order' => 99,
        ]);
        $privateUser = User::factory()->create(['firstname' => 'Private', 'lastname' => 'Admin', 'status' => 'active']);
        StaffProfile::factory()->create([
            'user_id' => $privateUser->id,
            'hospital_id' => $this->hospital->id,
            'staff_category' => 'administrative',
            'job_title' => 'Administrator',
            'public_is_visible' => true,
            'public_is_featured' => true,
            'public_slug' => 'private-admin',
        ]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('items.service', 2)
            ->has('items.department', 1)
            ->has('items.clinician', 1)
            ->where('items.clinician.0.slug', 'published-clinician-fixture'));

        $this->get('/services')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('items.service', 3)
            ->where('items.service.2.slug', 'public-unfeatured-service')
            ->missing('items.service.3'));

        $this->get('/departments')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('items.department', 2)
            ->where('items.department.1.slug', 'public-listing-department'));

        $this->get('/doctors')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('items.clinician', 2)
            ->where('items.clinician.1.slug', 'listing-doctor'));
    }

    public function test_unsafe_rich_text_is_sanitized_and_section_idor_is_rejected(): void
    {
        $admin = $this->userWithPermissions(['website.view', 'website.edit']);
        $item = PublicSiteItem::where('type', 'testimonial')->firstOrFail();
        $otherHospital = Hospital::factory()->create();
        $otherPage = PublicSitePage::create([
            'hospital_id' => $otherHospital->id,
            'slug' => 'other',
            'title' => 'Other',
            'template' => 'standard',
            'status' => 'draft',
            'draft_content' => [],
            'published_content' => [],
            'seo' => [],
        ]);
        $otherSection = $otherPage->sections()->create(['key' => 'services', 'type' => 'services', 'label' => 'Other', 'draft_content' => [], 'published_content' => []]);

        $this->actingAs($admin)->patch("/admin/public-website/items/{$item->id}", [
            'public_site_section_id' => $item->public_site_section_id,
            'type' => $item->type,
            'slug' => $item->slug,
            'title' => $item->title,
            'summary' => 'Clean',
            'draft_content' => ['body' => '<p onclick="alert(1)"><a href="javascript:alert(1)">Read</a><script>alert(1)</script></p>'],
            'status' => 'published',
            'is_enabled' => true,
            'is_featured' => true,
            'sort_order' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $body = $item->refresh()->draft_content['body'];
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('script', $body);
        $this->assertStringContainsString('href="#"', $body);
        $this->assertSame('published', $item->status);

        $this->actingAs($admin)->patch("/admin/public-website/items/{$item->id}", [
            'public_site_section_id' => $otherSection->id,
            'type' => $item->type,
            'slug' => $item->slug,
            'title' => $item->title,
            'summary' => 'Cross hospital',
            'draft_content' => ['body' => 'Cross hospital'],
            'status' => 'published',
            'is_enabled' => true,
            'is_featured' => true,
            'sort_order' => 1,
        ])->assertForbidden();
    }

    public function test_media_upload_validation_and_deletion_protection(): void
    {
        Storage::fake('public');
        $manager = $this->userWithPermissions(['website.view', 'website.manage_media']);

        $this->actingAs($manager)->post('/admin/public-website/media', [
            'title' => 'Care team',
            'alt_text' => 'Care team image',
            'image' => UploadedFile::fake()->image('team.jpg', 900, 600),
        ])->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('uploaded_media.url')
            ->assertSessionHas('uploaded_media.alt_text', 'Care team image');

        $media = PublicSiteMedia::firstOrFail();
        Storage::disk('public')->assertExists($media->path);
        $this->assertStringEndsWith('.jpg', $media->path);
        $this->assertStringContainsString("/public-site/media/{$media->id}/", $media->url);
        $this->get($media->url)->assertOk()->assertHeader('content-type', 'image/jpeg');

        $home = PublicSitePage::where('slug', 'home')->firstOrFail();
        $draft = $home->draft_content;
        $draft['footer']['referenced_image'] = "/storage/{$media->path}";
        $home->update([
            'draft_content' => $draft,
            'published_content' => $draft,
            'draft_seo' => ['title' => 'Home', 'description' => 'Home description', 'image' => "/storage/{$media->path}", 'image_alt' => 'Care team image'],
            'published_seo' => ['title' => 'Home', 'description' => 'Home description', 'image' => "/storage/{$media->path}", 'image_alt' => 'Care team image'],
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('site.footer.referenced_image', $media->url)
                ->where('page.seo.image', url($media->url)));

        $this->actingAs($manager)->post('/admin/public-website/media', [
            'title' => 'Unsafe',
            'alt_text' => 'Unsafe SVG',
            'image' => UploadedFile::fake()->create('unsafe.svg', 8, 'image/svg+xml'),
        ])->assertSessionHasErrors('image');

        $draft['footer']['referenced_image'] = $media->url;
        $home->update(['draft_content' => $draft]);

        $this->actingAs($manager)->delete("/admin/public-website/media/{$media->id}")->assertForbidden();
        $this->assertSame(4, $media->refresh()->usage_count);

        unset($draft['footer']['referenced_image']);
        $published = $home->published_content;
        unset($published['footer']['referenced_image']);
        $home->update([
            'draft_content' => $draft,
            'published_content' => $published,
            'draft_seo' => ['title' => 'Home', 'description' => 'Home description'],
            'published_seo' => ['title' => 'Home', 'description' => 'Home description'],
        ]);
        $this->actingAs($manager)->delete("/admin/public-website/media/{$media->id}")->assertRedirect();
        $this->assertDatabaseMissing('public_site_media', ['id' => $media->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'website.media_deleted']);
    }

    public function test_seeders_are_idempotent_and_public_pages_are_hospital_scoped(): void
    {
        $this->seed(PublicSiteSeeder::class);
        $this->seed(PublicSiteSeeder::class);

        $this->assertSame(1, PublicSitePage::where('hospital_id', $this->hospital->id)->where('slug', 'home')->count());
        $this->assertSame(0, PublicSiteItem::where('hospital_id', $this->hospital->id)->where('slug', 'general-consultation')->count());

        $otherHospital = Hospital::factory()->create(['is_active' => false]);
        PublicSitePage::create([
            'hospital_id' => $otherHospital->id,
            'slug' => 'home',
            'title' => 'Wrong Hospital',
            'template' => 'home',
            'status' => 'published',
            'draft_content' => [],
            'published_content' => ['summary' => 'Wrong hospital content'],
            'seo' => [],
            'published_at' => now(),
        ]);

        $this->get('/')->assertOk()->assertDontSee('Wrong hospital content');
    }

    public function test_admin_public_website_pages_render_with_inertia(): void
    {
        $admin = $this->userWithPermissions(['website.view', 'website.edit', 'website.publish', 'website.manage_media']);
        $home = PublicSitePage::where('slug', 'home')->firstOrFail();

        $this->actingAs($admin)->get('/admin/public-website')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/PublicWebsite/Index')->has('pages'));

        $this->actingAs($admin)->get("/admin/public-website/pages/{$home->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/PublicWebsite/Edit')
                ->has('page.sections')
                ->where('can_manage_media', true)
                ->where('can_view_json', false));

        $superadmin = $this->userWithPermissions(['website.view', 'website.edit']);
        $superadmin->syncRoles(['superadmin']);

        $this->actingAs($superadmin)->get("/admin/public-website/pages/{$home->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can_view_json', true));
    }

    public function test_admin_services_cms_page_creates_public_featured_services(): void
    {
        $admin = $this->userWithPermissions(['billing.catalogue.view', 'billing.catalogue.manage']);
        $department = Department::where('hospital_id', $this->hospital->id)->where('name', 'Cardiology')->firstOrFail();

        $this->actingAs($admin)->get('/admin/services')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Services/Index')
                ->has('services')
                ->has('categories')
                ->has('departments'));

        $this->actingAs($admin)->post('/admin/services', [
            'department_id' => $department->id,
            'name' => 'Public physiotherapy',
            'description' => 'Internal physiotherapy description.',
            'is_active' => true,
            'public_is_visible' => true,
            'public_is_featured' => true,
            'public_name' => 'Physiotherapy',
            'public_description' => 'Movement and rehabilitation support.',
            'public_icon' => 'stethoscope',
            'public_display_order' => 3,
            'facility_ids' => [],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('billable_service_categories', [
            'hospital_id' => $this->hospital->id,
            'code' => 'PUBLIC',
        ]);
        $this->assertDatabaseHas('billable_services', [
            'hospital_id' => $this->hospital->id,
            'code' => 'PUBLIC_PHYSIOTHERAPY',
            'public_slug' => 'physiotherapy',
            'public_is_visible' => true,
            'public_is_featured' => true,
        ]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('items.service', 3)
            ->where('items.service.2.slug', 'physiotherapy'));

        $this->get('/services')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('items.service', 3)
            ->where('items.service.2.title', 'Physiotherapy'));
    }

    public function test_admin_public_website_editor_handles_page_with_no_sections_items_or_media(): void
    {
        $admin = $this->userWithPermissions(['website.view', 'website.edit']);
        $page = PublicSitePage::create([
            'hospital_id' => $this->hospital->id,
            'slug' => 'empty-editor-page',
            'title' => 'Empty Editor Page',
            'template' => 'standard',
            'status' => 'draft',
            'draft_content' => null,
            'published_content' => null,
            'seo' => null,
            'draft_seo' => null,
        ]);

        $this->actingAs($admin)->get("/admin/public-website/pages/{$page->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/PublicWebsite/Edit')
                ->has('page.sections', 0)
                ->where('page.draft_content.navigation.items', [])
                ->where('page.draft_content.footer.badges', [])
                ->where('page.draft_content.theme.accent', 'calm')
                ->where('page.seo.title', '')
                ->where('media', []));
    }

    public function test_admin_public_website_editor_handles_partially_configured_content(): void
    {
        $admin = $this->userWithPermissions(['website.view', 'website.edit']);
        $page = PublicSitePage::create([
            'hospital_id' => $this->hospital->id,
            'slug' => 'partial-editor-page',
            'title' => 'Partial Editor Page',
            'template' => 'home',
            'status' => 'draft',
            'draft_content' => ['navigation' => []],
            'seo' => ['canonical' => '/partial-editor-page'],
        ]);
        $section = $page->sections()->create([
            'key' => 'hero',
            'type' => 'hero_slider',
            'label' => 'Hero slider',
            'draft_content' => [],
            'published_content' => null,
        ]);
        PublicSiteItem::create([
            'hospital_id' => $this->hospital->id,
            'public_site_section_id' => $section->id,
            'draft_public_site_section_id' => $section->id,
            'type' => 'service',
            'draft_type' => 'service',
            'slug' => 'partial-service',
            'draft_slug' => 'partial-service',
            'title' => 'Partial Service',
            'draft_title' => 'Partial Service',
            'status' => 'draft',
            'draft_content' => null,
        ]);

        $this->actingAs($admin)->get("/admin/public-website/pages/{$page->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/PublicWebsite/Edit')
                ->where('page.draft_content.navigation.items', [])
                ->where('page.draft_content.footer.summary', '')
                ->where('page.seo.canonical_url', '/partial-editor-page')
                ->where('page.sections.0.draft_content.slides', [])
                ->where('page.sections.0.items.0.draft_content.description', '')
                ->where('page.sections.0.items.0.public_site_section_id', $section->id));
    }

    public function test_stale_public_website_page_id_redirects_to_admin_index(): void
    {
        $admin = $this->userWithPermissions(['website.view', 'website.edit']);

        $this->actingAs($admin)->get('/admin/public-website/pages/999999')
            ->assertRedirect(route('admin.public-website.index'))
            ->assertSessionHas('warning', 'That public website page no longer exists. Choose an available page to manage.');
    }

    public function test_legacy_cms_admin_routes_are_archived(): void
    {
        $admin = $this->userWithPermissions(['website.view']);

        $this->actingAs($admin)->get('/admin/pages')->assertGone();
        $this->actingAs($admin)->get('/admin/pages/1/edit')->assertGone();
    }

    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create(['access_level' => 'admin']);
        $user->syncRoles(['admin']);
        $user->givePermissionTo($permissions);

        StaffProfile::factory()->create([
            'user_id' => $user->id,
            'hospital_id' => $this->hospital->id,
            'staff_category' => 'administrative',
        ]);

        return $user;
    }

    private function seedPublicItemFixtures(): void
    {
        $category = BillableServiceCategory::create([
            'hospital_id' => $this->hospital->id,
            'name' => 'Clinical services',
            'code' => 'CLINICAL',
            'is_active' => true,
        ]);

        foreach ([
            ['slug' => 'general-consultation', 'name' => 'General consultation', 'description' => 'Approved public service details.', 'order' => 1],
            ['slug' => 'emergency-information', 'name' => 'Emergency information', 'description' => 'Approved urgent-contact details.', 'order' => 2],
        ] as $service) {
            BillableService::create([
                'hospital_id' => $this->hospital->id,
                'billable_service_category_id' => $category->id,
                'code' => strtoupper(str_replace('-', '_', $service['slug'])),
                'name' => $service['name'],
                'description' => 'Internal billing description.',
                'is_active' => true,
                'is_tax_exempt' => false,
                'tax_rate_basis_points' => 0,
                'is_discount_eligible' => true,
                'public_is_visible' => true,
                'public_is_featured' => true,
                'public_slug' => $service['slug'],
                'public_name' => $service['name'],
                'public_description' => $service['description'],
                'public_icon' => 'stethoscope',
                'public_display_order' => $service['order'],
            ]);
        }

        $clinicianUser = User::factory()->create([
            'firstname' => 'Published',
            'lastname' => 'Clinician',
            'status' => 'active',
        ]);
        StaffProfile::factory()->create([
            'user_id' => $clinicianUser->id,
            'hospital_id' => $this->hospital->id,
            'staff_category' => 'clinical',
            'job_title' => 'Clinician',
            'employment_status' => 'active',
            'is_active' => true,
            'public_is_visible' => true,
            'public_is_featured' => true,
            'public_slug' => 'published-clinician-fixture',
            'public_display_name' => 'Published Clinician Fixture',
            'public_specialty' => 'Clinician',
            'public_summary' => 'Approved clinician biography.',
            'public_photo_path' => '/frontend/images/doctors/prof.jpg',
            'public_photo_alt' => 'Clinician portrait',
            'public_display_order' => 1,
        ]);

        $home = PublicSitePage::where('hospital_id', $this->hospital->id)->where('slug', 'home')->firstOrFail();
        $sections = $home->sections()->get()->keyBy('key');
        $fixtures = [
            ['section_key' => 'testimonials', 'type' => 'testimonial', 'slug' => 'approved-testimonial-fixture', 'title' => 'Approved testimonial fixture', 'summary' => 'Approved testimonial summary.', 'content' => ['text' => 'Approved public statement.', 'approved' => true], 'sort_order' => 1],
            ['section_key' => 'news', 'type' => 'article', 'slug' => 'published-article-fixture', 'title' => 'Published article fixture', 'summary' => 'Approved article summary.', 'content' => ['body' => 'Approved article body.', 'author' => 'Communications'], 'sort_order' => 1],
        ];

        foreach ($fixtures as $fixture) {
            $presentableType = $fixture['type'] === 'department' ? Department::class : null;
            $presentableId = $fixture['type'] === 'department' ? Department::where('hospital_id', $this->hospital->id)->where('name', $fixture['title'])->value('id') : null;

            PublicSiteItem::updateOrCreate(
                ['hospital_id' => $this->hospital->id, 'type' => $fixture['type'], 'slug' => $fixture['slug']],
                [
                    'public_site_section_id' => $sections[$fixture['section_key']]?->id,
                    'draft_public_site_section_id' => $sections[$fixture['section_key']]?->id,
                    'published_public_site_section_id' => $sections[$fixture['section_key']]?->id,
                    'presentable_type' => $presentableType,
                    'presentable_id' => $presentableId,
                    'title' => $fixture['title'],
                    'draft_title' => $fixture['title'],
                    'published_title' => $fixture['title'],
                    'summary' => $fixture['summary'],
                    'draft_summary' => $fixture['summary'],
                    'published_summary' => $fixture['summary'],
                    'draft_type' => $fixture['type'],
                    'published_type' => $fixture['type'],
                    'draft_slug' => $fixture['slug'],
                    'published_slug' => $fixture['slug'],
                    'draft_content' => $fixture['content'],
                    'published_content' => $fixture['content'],
                    'status' => 'published',
                    'is_enabled' => true,
                    'draft_is_enabled' => true,
                    'published_is_enabled' => true,
                    'is_featured' => true,
                    'draft_is_featured' => true,
                    'published_is_featured' => true,
                    'sort_order' => $fixture['sort_order'],
                    'draft_sort_order' => $fixture['sort_order'],
                    'published_sort_order' => $fixture['sort_order'],
                    'published_version' => 1,
                    'published_at' => now(),
                ],
            );
        }
    }
}
