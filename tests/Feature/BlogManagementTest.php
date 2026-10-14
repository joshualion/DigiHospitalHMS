<?php

namespace Tests\Feature;

use App\Models\BlogComment;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalSetting;
use App\Models\PublicSiteItem;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PublicSiteSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogManagementTest extends TestCase
{
    use RefreshDatabase;

    private Hospital $hospital;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->hospital = Hospital::factory()->create(['display_name' => 'Blog Test Hospital']);
        $facility = Facility::factory()->create(['hospital_id' => $this->hospital->id, 'is_primary' => true]);
        HospitalSetting::create(['hospital_id' => $this->hospital->id, 'default_facility_id' => $facility->id]);
        $this->seed(PublicSiteSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['access_level' => 'admin']);
        $user->assignRole('superadmin');
        return $user;
    }

    private function postData(): array
    {
        return [
            'title' => 'Hand washing for everyone', 'slug' => 'hand-washing',
            'summary' => 'Practical infection prevention information',
            'subtitle' => 'Simple measures that help', 'body' => 'Wash your hands regularly.',
            'author' => 'Clinical team', 'category' => 'Health education',
            'tags' => 'prevention, hygiene', 'seo_title' => 'Hand washing guide',
            'seo_description' => 'Learn about practical hand hygiene.',
            'cover' => '', 'cover_alt' => '', 'thumbnail' => '', 'thumbnail_alt' => '',
            'comments_enabled' => true, 'is_featured' => false,
        ];
    }

    public function test_blog_post_is_saved_as_draft_then_published(): void
    {
        $this->actingAs($this->admin())->post('/admin/news', $this->postData())
            ->assertRedirect()->assertSessionHasNoErrors();
        $post = PublicSiteItem::where('draft_slug', 'hand-washing')->firstOrFail();
        $this->assertSame('draft', $post->status);

        $this->get('/news/hand-washing')->assertNotFound();
        $this->post("/admin/news/{$post->id}/publish")->assertRedirect();
        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertSame('Hand washing guide', $post->published_content['seo_title']);
        $this->get('/news/hand-washing')->assertOk();
        $this->assertDatabaseHas('public_site_revisions', ['revisionable_id' => $post->id, 'action' => 'publish']);
    }

    public function test_only_approved_comments_are_public_and_moderation_is_versioned(): void
    {
        $this->actingAs($this->admin())->post('/admin/news', $this->postData())->assertRedirect();
        $post = PublicSiteItem::where('draft_slug', 'hand-washing')->firstOrFail();
        $this->post("/admin/news/{$post->id}/publish")->assertRedirect();
        auth()->logout();

        $this->post('/news/hand-washing/comments', [
            'name' => 'Hospital Visitor', 'email' => 'visitor@example.com',
            'body' => 'Thank you for this important information',
            'website' => '',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $comment = BlogComment::firstOrFail();
        $this->assertSame('pending', $comment->status);
        $this->get('/news/hand-washing')->assertOk()->assertDontSee('Thank you for this important information');

        $this->actingAs($this->admin())->patch("/admin/news/comments/{$comment->id}", ['status' => 'approved'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('blog_comment_reviews', ['blog_comment_id' => $comment->id, 'previous_status' => 'pending', 'status' => 'approved']);
        $this->get('/news/hand-washing')->assertOk()->assertSee('Thank you for this important information');

        $this->patch("/admin/news/comments/{$comment->id}", ['status' => 'rejected'])->assertRedirect();
        $this->get('/news/hand-washing')->assertOk()->assertDontSee('Thank you for this important information');
    }

    public function test_disabled_comments_are_rejected(): void
    {
        $postData = array_merge($this->postData(), ['comments_enabled' => false]);
        $this->actingAs($this->admin())->post('/admin/news', $postData)->assertRedirect();
        $post = PublicSiteItem::where('draft_slug', 'hand-washing')->firstOrFail();
        $this->post("/admin/news/{$post->id}/publish")->assertRedirect();
        auth()->logout();

        $this->post('/news/hand-washing/comments', [
            'name' => 'Visitor', 'email' => 'visitor@example.com', 'body' => 'Disabled here', 'website' => '',
        ])->assertForbidden();
        $this->assertDatabaseCount('blog_comments', 0);
    }
}
