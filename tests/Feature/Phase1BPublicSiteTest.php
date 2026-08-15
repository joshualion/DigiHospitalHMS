<?php

namespace Tests\Feature;

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
        Department::factory()->create(['hospital_id' => $this->hospital->id, 'name' => 'Cardiology', 'category' => 'clinical']);

        $this->seed(PublicSiteSeeder::class);
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
                ->has('items.service'));
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

        $this->assertSame('Draft Home', $page->refresh()->title);
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

    public function test_section_order_visibility_service_department_clinician_and_testimonial_rules(): void
    {
        $home = PublicSitePage::where('slug', 'home')->with('sections')->firstOrFail();
        $section = $home->sections->firstWhere('type', 'services');
        $service = PublicSiteItem::where('type', 'service')->firstOrFail();
        $department = PublicSiteItem::where('type', 'department')->firstOrFail();
        $clinician = PublicSiteItem::where('type', 'clinician')->firstOrFail();
        $testimonial = PublicSiteItem::where('type', 'testimonial')->firstOrFail();

        $this->assertTrue($section->is_enabled);
        $this->assertSame('published', $service->status);
        $this->assertNotNull($department->presentable_id);
        $this->assertArrayNotHasKey('personal_phone', $clinician->published_content ?? []);
        $this->assertStringContainsString('demonstration only', strtolower($testimonial->summary));

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('items.service', 2)
                ->where('items.service.0.slug', 'general-consultation')
                ->where('items.service.1.slug', 'emergency-information'));

        $service->update(['status' => 'draft', 'is_enabled' => false]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('items.service', 1)
                ->where('items.service.0.slug', 'emergency-information'));
    }

    public function test_unsafe_rich_text_is_sanitized_and_section_idor_is_rejected(): void
    {
        $admin = $this->userWithPermissions(['website.view', 'website.edit']);
        $item = PublicSiteItem::where('type', 'service')->firstOrFail();
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
        ])->assertRedirect()->assertSessionHasNoErrors();

        $media = PublicSiteMedia::firstOrFail();
        Storage::disk('public')->assertExists($media->path);
        $this->assertStringEndsWith('.jpg', $media->path);

        $this->actingAs($manager)->post('/admin/public-website/media', [
            'title' => 'Unsafe',
            'alt_text' => 'Unsafe SVG',
            'image' => UploadedFile::fake()->create('unsafe.svg', 8, 'image/svg+xml'),
        ])->assertSessionHasErrors('image');

        $media->update(['usage_count' => 1]);
        $this->actingAs($manager)->delete("/admin/public-website/media/{$media->id}")->assertForbidden();
    }

    public function test_seeders_are_idempotent_and_public_pages_are_hospital_scoped(): void
    {
        $this->seed(PublicSiteSeeder::class);
        $this->seed(PublicSiteSeeder::class);

        $this->assertSame(1, PublicSitePage::where('hospital_id', $this->hospital->id)->where('slug', 'home')->count());
        $this->assertSame(1, PublicSiteItem::where('hospital_id', $this->hospital->id)->where('slug', 'general-consultation')->count());

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
            ->assertInertia(fn (Assert $page) => $page->component('Admin/PublicWebsite/Edit')->has('page.sections'));
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
}
