<?php

namespace App\Http\Controllers\Admin;

use App\Models\BlogComment;
use App\Models\PublicSiteItem;
use App\Models\PublicSiteMedia;
use App\Models\PublicSitePage;
use App\Models\PublicSiteRevision;
use App\Services\AuditService;
use App\Services\PublicSitePublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends FoundationController
{
    public function index(): Response
    {
        abort_unless(request()->user()->hasRole('superadmin') || request()->user()->can('website.view'), 403);
        $hospital = $this->currentHospital();
        $posts = PublicSiteItem::where('hospital_id', $hospital->id)
            ->where(fn ($query) => $query->where('type', 'article')->orWhere('draft_type', 'article')->orWhere('published_type', 'article'))
            ->withCount(['comments as pending_comments_count' => fn ($query) => $query->where('status', 'pending')])
            ->latest()->get();

        return Inertia::render('Admin/Blog/Index', [
            'posts' => $posts->map(fn ($post) => [
                'id' => $post->id, 'title' => $post->draft_title ?? $post->title,
                'slug' => $post->draft_slug ?? $post->slug,
                'status' => $post->status, 'published_at' => $post->published_at?->toDateTimeString(),
                'comments' => $post->pending_comments_count,
            ]),
        ]);
    }

    public function create(): Response
    {
        abort_unless(request()->user()->hasRole('superadmin') || request()->user()->can('website.edit'), 403);
        return $this->editor(null);
    }

    public function edit(PublicSiteItem $post): Response
    {
        $this->assertPost($post);
        $this->authorize('update', $post);
        return $this->editor($post);
    }

    private function editor(?PublicSiteItem $post): Response
    {
        $hospital = $this->currentHospital();
        return Inertia::render('Admin/Blog/Edit', [
            'post' => $post ? [
                'id' => $post->id, 'title' => $post->draft_title ?? $post->title,
                'slug' => $post->draft_slug ?? $post->slug, 'summary' => $post->draft_summary ?? $post->summary,
                'content' => $post->draft_content ?? [], 'status' => $post->status,
                'published_at' => $post->published_at?->toDateTimeString(),
                'is_featured' => (bool) ($post->draft_is_featured ?? $post->is_featured),
            ] : null,
            'media' => PublicSiteMedia::where('hospital_id', $hospital->id)->latest()->get(['id', 'title', 'path', 'disk', 'alt_text']),
            'revisions' => $post ? PublicSiteRevision::where('revisionable_type', PublicSiteItem::class)->where('revisionable_id', $post->id)->latest('id')->limit(25)->get(['id','version','action','created_at']) : [],
            'comments' => $post ? BlogComment::where('public_site_item_id', $post->id)->latest()->limit(100)->get(['id','name','body','status','created_at','moderation_note'])->map(fn ($comment) => array_merge($comment->toArray(), [
                'reviews' => DB::table('blog_comment_reviews')->where('blog_comment_id', $comment->id)->orderByDesc('id')->limit(20)->get(['previous_status', 'status', 'note', 'created_at']),
            ])) : [],
            'can_publish' => request()->user()->hasRole('superadmin') || request()->user()->can('website.publish'),
        ]);
    }

    private function assertPost(PublicSiteItem $post): void
    {
        abort_unless(in_array('article', array_filter([$post->type, $post->draft_type, $post->published_type]), true), 404);
        abort_unless($post->hospital_id === $this->currentHospital()->id, 404);
    }

    private function rules(): array
    {
        return [
            'title' => ['required','string','max:255'],
            'slug' => ['nullable','string','max:255'],
            'summary' => ['nullable','string','max:1000'],
            'subtitle' => ['nullable','string','max:300'],
            'body' => ['required','string','max:100000'],
            'author' => ['nullable','string','max:150'],
            'category' => ['nullable','string','max:100'],
            'tags' => ['nullable','string','max:500'],
            'seo_title' => ['nullable','string','max:255'],
            'seo_description' => ['nullable','string','max:320'],
            'cover' => ['nullable','string','max:500'],
            'cover_alt' => ['nullable','string','max:255'],
            'thumbnail' => ['nullable','string','max:500'],
            'thumbnail_alt' => ['nullable','string','max:255'],
            'comments_enabled' => ['required','boolean'],
            'is_featured' => ['required','boolean'],
        ];
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        abort_unless($request->user()->hasRole('superadmin') || $request->user()->can('website.edit'), 403);
        $hospital = $this->currentHospital();
        $data = $request->validate($this->rules());
        $slug = $this->availableSlug($hospital->id, $data['slug'] ?: $data['title']);
        $post = PublicSiteItem::create([
            'hospital_id' => $hospital->id, 'type' => 'article', 'draft_type' => 'article',
            'title' => $data['title'], 'draft_title' => $data['title'],
            'slug' => $slug, 'draft_slug' => $slug,
            'summary' => $data['summary'] ?? '', 'draft_summary' => $data['summary'] ?? '',
            'draft_content' => $this->content($data),
            'status' => 'draft', 'is_enabled' => false, 'draft_is_enabled' => true,
            'is_featured' => false, 'draft_is_featured' => $data['is_featured'],
            'sort_order' => 0, 'draft_sort_order' => 0,
        ]);
        $audit->record('blog.post_created', $post, null, ['title' => $post->title]);
        return redirect()->route('admin.news.edit', $post)->with('success', 'Post saved as draft.');
    }

    public function update(Request $request, PublicSiteItem $post, AuditService $audit): RedirectResponse
    {
        $this->assertPost($post);
        $this->authorize('update', $post);
        $data = $request->validate($this->rules());
        $slug = $this->availableSlug($post->hospital_id, $data['slug'] ?: $data['title'], $post->id);
        $before = $post->draftSnapshot();
        $post->update([
            'draft_title' => $data['title'], 'draft_slug' => $slug,
            'draft_summary' => $data['summary'] ?? '',
            'draft_content' => $this->content($data),
            'draft_type' => 'article', 'draft_is_enabled' => true,
            'draft_is_featured' => $data['is_featured'],
        ]);
        $audit->record('blog.post_updated', $post, $before, $post->fresh()->draftSnapshot());
        return back()->with('success', 'Draft saved. Published content is unchanged until you publish.');
    }

    public function publish(PublicSiteItem $post, PublicSitePublisher $publisher): RedirectResponse
    {
        $this->assertPost($post);
        $this->authorize('publish', $post);
        $publisher->publish($post, request()->user());
        return back()->with('success', 'Blog post published.');
    }

    public function unpublish(PublicSiteItem $post, PublicSitePublisher $publisher): RedirectResponse
    {
        $this->assertPost($post);
        $this->authorize('unpublish', $post);
        $publisher->unpublish($post, request()->user());
        return back()->with('success', 'Blog post unpublished.');
    }

    public function restore(PublicSiteItem $post, PublicSiteRevision $revision, PublicSitePublisher $publisher): RedirectResponse
    {
        $this->assertPost($post);
        $this->authorize('update', $post);
        abort_unless($revision->hospital_id === $post->hospital_id && $revision->revisionable_type === PublicSiteItem::class && $revision->revisionable_id === $post->id, 404);
        $publisher->restore($revision, request()->user());
        return back()->with('success', 'Revision restored into draft. Publish to make it live.');
    }

    public function moderate(Request $request, BlogComment $comment, AuditService $audit): RedirectResponse
    {
        abort_unless($comment->hospital_id === $this->currentHospital()->id, 404);
        abort_unless($request->user()->hasRole('superadmin') || $request->user()->can('website.edit'), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['pending','approved','rejected'])], 'note' => ['nullable','string','max:1000']]);
        $before = $comment->only(['status','moderation_note']);
        $comment->update(['status' => $data['status'], 'moderation_note' => $data['note'] ?? '', 'moderated_by' => $request->user()->id, 'moderated_at' => now()]);
        DB::table('blog_comment_reviews')->insert([
            'blog_comment_id' => $comment->id,
            'hospital_id' => $comment->hospital_id,
            'reviewed_by' => $request->user()->id,
            'previous_status' => $before['status'],
            'status' => $data['status'],
            'note' => $data['note'] ?? null,
            'created_at' => now(),
        ]);
        $audit->record('blog.comment_moderated', $comment, $before, $comment->only(['status','moderation_note']));
        return back()->with('success', 'Comment moderation saved.');
    }

    private function content(array $data): array
    {
        return [
            'subtitle' => $data['subtitle'] ?? '', 'body' => strip_tags($data['body']),
            'excerpt' => $data['summary'] ?? '', 'author' => $data['author'] ?? '',
            'category' => $data['category'] ?? '', 'tags' => $data['tags'] ?? '',
            'seo_title' => $data['seo_title'] ?? '', 'seo_description' => $data['seo_description'] ?? '',
            'image' => $data['cover'] ?? '', 'alt' => $data['cover_alt'] ?? '',
            'thumbnail' => $data['thumbnail'] ?? '', 'thumbnail_alt' => $data['thumbnail_alt'] ?? '',
            'comments_enabled' => (bool) $data['comments_enabled'],
        ];
    }

    private function availableSlug(int $hospitalId, string $value, ?int $except = null): string
    {
        $base = Str::slug($value) ?: 'post';
        $candidate = $base;
        $number = 2;
        while (PublicSiteItem::where('hospital_id', $hospitalId)->where('id', '!=', $except ?? 0)
            ->where(fn ($query) => $query->where('slug', $candidate)->orWhere('draft_slug', $candidate)->orWhere('published_slug', $candidate))
            ->exists()) {
            $candidate = $base.'-'.$number++;
        }
        return $candidate;
    }
}
