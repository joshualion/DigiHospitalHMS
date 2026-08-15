<?php

namespace App\Http\Controllers\Admin;

use App\Models\PublicSiteItem;
use App\Models\PublicSiteMedia;
use App\Models\PublicSitePage;
use App\Models\PublicSiteRevision;
use App\Models\PublicSiteSection;
use App\Services\AuditService;
use App\Services\PublicSitePublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PublicWebsiteController extends FoundationController
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PublicSitePage::class);
        $hospital = $this->currentHospital();

        $pages = PublicSitePage::withCount('sections')
            ->where('hospital_id', $hospital->id)
            ->orderByRaw("slug = 'home' desc")
            ->orderBy('title')
            ->get();

        return Inertia::render('Admin/PublicWebsite/Index', [
            'pages' => $pages,
            'media' => PublicSiteMedia::where('hospital_id', $hospital->id)->latest()->paginate(8),
            'stats' => [
                'published_pages' => $pages->where('status', 'published')->count(),
                'draft_pages' => $pages->where('status', 'draft')->count(),
                'media_count' => PublicSiteMedia::where('hospital_id', $hospital->id)->count(),
                'revision_count' => PublicSiteRevision::where('hospital_id', $hospital->id)->count(),
            ],
        ]);
    }

    public function edit(PublicSitePage $page): Response
    {
        $this->authorize('view', $page);
        $page->load(['sections.items', 'revisions.creator:id,firstname,lastname,email']);

        return Inertia::render('Admin/PublicWebsite/Edit', [
            'page' => $page,
            'preview_url' => URL::temporarySignedRoute('public.preview', now()->addMinutes(30), ['page' => $page]),
            'media' => PublicSiteMedia::where('hospital_id', $page->hospital_id)->latest()->get(),
            'item_types' => ['service', 'department', 'clinician', 'testimonial', 'article'],
        ]);
    }

    public function updatePage(Request $request, PublicSitePage $page, AuditService $audit): RedirectResponse
    {
        $this->authorize('update', $page);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'draft_content' => ['required', 'array'],
            'seo' => ['nullable', 'array'],
        ]);

        $validated['draft_content'] = $this->sanitizePayload($validated['draft_content']);
        $validated['seo'] = $this->sanitizePayload($validated['seo'] ?? []);
        $before = $page->only(['title', 'draft_content', 'seo']);
        $page->update($validated);

        $audit->record('website.page_updated', $page, $before, $page->only(['title', 'draft_content', 'seo']));

        return back()->with('success', 'Draft page saved.');
    }

    public function updateSection(Request $request, PublicSiteSection $section, AuditService $audit): RedirectResponse
    {
        $this->authorize('update', $section);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_enabled' => ['required', 'boolean'],
            'draft_content' => ['required', 'array'],
        ]);

        $validated['draft_content'] = $this->sanitizePayload($validated['draft_content']);
        $before = $section->only(array_keys($validated));
        $section->update($validated);

        $audit->record('website.section_updated', $section, $before, $section->only(array_keys($validated)));

        return back()->with('success', 'Draft section saved.');
    }

    public function updateItem(Request $request, PublicSiteItem $item, AuditService $audit): RedirectResponse
    {
        $this->authorize('update', $item);

        $validated = $request->validate($this->itemRules($item));
        $this->ensureSectionBelongsToHospital($validated['public_site_section_id'] ?? null, $item->hospital_id);
        $validated['draft_content'] = $this->sanitizePayload($validated['draft_content']);
        $validated['status'] = $item->status;
        $before = $item->only(array_keys($validated));
        $item->update($validated);

        $audit->record("website.{$item->type}_updated", $item, $before, $item->only(array_keys($validated)));

        return back()->with('success', 'Draft item saved.');
    }

    public function storeItem(Request $request, PublicSitePage $page, AuditService $audit): RedirectResponse
    {
        $this->authorize('update', $page);

        $validated = $request->validate($this->itemRules());
        $this->ensureSectionBelongsToPage($validated['public_site_section_id'] ?? null, $page);
        $validated['hospital_id'] = $page->hospital_id;
        $validated['draft_content'] = $this->sanitizePayload($validated['draft_content']);
        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['title']);
        $validated['status'] = 'draft';

        $item = PublicSiteItem::create($validated);
        $audit->record("website.{$item->type}_created", $item, null, $item->toArray());

        return back()->with('success', 'Draft item created.');
    }

    public function updateTheme(Request $request, PublicSitePage $page, AuditService $audit): RedirectResponse
    {
        $this->authorize('update', $page);
        abort_unless($request->user()->can('website.manage_theme'), 403);

        $validated = $request->validate([
            'appearance' => ['required', Rule::in(['light', 'dark', 'system'])],
            'accent' => ['required', Rule::in(['calm-blue', 'healing-green', 'warm-gold', 'vital-red'])],
            'allowed_accents' => ['required', 'array', 'min:1'],
            'allowed_accents.*' => ['required', Rule::in(['calm-blue', 'healing-green', 'warm-gold', 'vital-red'])],
            'show_switcher' => ['required', 'boolean'],
        ]);

        $draft = $page->draft_content ?? [];
        $before = $draft['theme'] ?? null;
        $draft['theme'] = [
            'appearance' => $validated['appearance'],
            'accent' => $validated['accent'],
            'allowed_accents' => array_values(array_unique($validated['allowed_accents'])),
            'show_switcher' => (bool) $validated['show_switcher'],
        ];

        $page->update(['draft_content' => $draft]);
        $audit->record('website.theme_updated', $page, $before, $draft['theme']);

        return back()->with('success', 'Theme defaults saved to draft.');
    }

    public function publishPage(PublicSitePage $page, PublicSitePublisher $publisher): RedirectResponse
    {
        $this->authorize('publish', $page);
        $page->sections->each(fn (PublicSiteSection $section) => $publisher->publish($section, request()->user()));
        $publisher->publish($page, request()->user());

        return back()->with('success', 'Page published.');
    }

    public function publishItem(PublicSiteItem $item, PublicSitePublisher $publisher): RedirectResponse
    {
        $this->authorize('publish', $item);
        $publisher->publish($item, request()->user());

        return back()->with('success', 'Item published.');
    }

    public function unpublishPage(PublicSitePage $page, PublicSitePublisher $publisher): RedirectResponse
    {
        $this->authorize('unpublish', $page);
        $publisher->unpublish($page, request()->user());

        return back()->with('success', 'Page unpublished.');
    }

    public function restoreRevision(PublicSiteRevision $revision, PublicSitePublisher $publisher): RedirectResponse
    {
        $this->authorize('restore', $revision);
        $publisher->restore($revision, request()->user());

        return back()->with('success', 'Revision restored into draft.');
    }

    public function uploadMedia(Request $request, AuditService $audit): RedirectResponse
    {
        $this->authorize('create', PublicSiteMedia::class);
        $hospital = $this->currentHospital();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'alt_text' => ['required', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1000'],
            'credit' => ['nullable', 'string', 'max:255'],
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:4096'],
        ]);

        $file = $validated['image'];
        $info = getimagesize($file->getRealPath());
        abort_if($info === false || ($info[0] ?? 0) > 4000 || ($info[1] ?? 0) > 4000, 422, 'Image dimensions are invalid or too large.');

        $extension = strtolower($file->extension());
        $path = $file->storeAs(
            "public-site/{$hospital->id}",
            Str::uuid()->toString().'.'.$extension,
            'public',
        );

        $media = PublicSiteMedia::create([
            'hospital_id' => $hospital->id,
            'title' => $validated['title'],
            'alt_text' => $validated['alt_text'],
            'caption' => $validated['caption'] ?? null,
            'credit' => $validated['credit'] ?? null,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'extension' => $extension,
            'size' => $file->getSize(),
            'width' => $info[0],
            'height' => $info[1],
            'uploaded_by' => $request->user()->id,
        ]);

        $audit->record('website.media_uploaded', $media, null, $media->except(['path']), actor: $request->user());

        return back()->with('success', 'Media uploaded.');
    }

    public function deleteMedia(PublicSiteMedia $media, AuditService $audit): RedirectResponse
    {
        $this->authorize('delete', $media);
        $before = $media->except(['path']);
        Storage::disk($media->disk)->delete($media->path);
        $media->delete();

        $audit->record('website.media_deleted', null, $before, null, actor: request()->user());

        return back()->with('success', 'Media deleted.');
    }

    private function itemRules(?PublicSiteItem $item = null): array
    {
        return [
            'public_site_section_id' => ['nullable', 'exists:public_site_sections,id'],
            'type' => ['required', Rule::in(['service', 'department', 'clinician', 'testimonial', 'article'])],
            'slug' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'draft_content' => ['required', 'array'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'is_enabled' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    private function ensureSectionBelongsToPage(?int $sectionId, PublicSitePage $page): void
    {
        if ($sectionId === null) {
            return;
        }

        abort_unless(
            PublicSiteSection::whereKey($sectionId)
                ->where('public_site_page_id', $page->id)
                ->whereHas('page', fn ($query) => $query->where('hospital_id', $page->hospital_id))
                ->exists(),
            403,
        );
    }

    private function ensureSectionBelongsToHospital(?int $sectionId, int $hospitalId): void
    {
        if ($sectionId === null) {
            return;
        }

        abort_unless(
            PublicSiteSection::whereKey($sectionId)
                ->whereHas('page', fn ($query) => $query->where('hospital_id', $hospitalId))
                ->exists(),
            403,
        );
    }

    private function sanitizePayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->sanitizePayload($value);
            } elseif (is_string($value)) {
                $payload[$key] = $this->sanitizeText($value);
            }
        }

        return $payload;
    }

    private function sanitizeText(string $value): string
    {
        $value = strip_tags($value, '<p><br><strong><em><ul><ol><li><a>');
        $value = preg_replace('/\s(on\w+|style)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $value) ?? $value;
        $value = preg_replace('/(href)\s*=\s*([\'"])\s*(javascript:|data:)[^\'"]*\2/i', 'href="#"', $value) ?? $value;

        return trim($value);
    }
}
