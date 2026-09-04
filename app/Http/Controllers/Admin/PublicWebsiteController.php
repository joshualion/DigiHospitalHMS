<?php

namespace App\Http\Controllers\Admin;

use App\Models\BillableService;
use App\Models\Department;
use App\Models\PublicSiteItem;
use App\Models\PublicSiteMedia;
use App\Models\PublicSitePage;
use App\Models\PublicSiteRevision;
use App\Models\PublicSiteSection;
use App\Models\StaffProfile;
use App\Services\AuditService;
use App\Services\PublicSiteMediaUsage;
use App\Services\PublicSitePublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PublicWebsiteController extends FoundationController
{
    public function index(Request $request, PublicSiteMediaUsage $mediaUsage): Response
    {
        $this->authorize('viewAny', PublicSitePage::class);
        $hospital = $this->currentHospital();
        $mediaUsage->refreshHospital($hospital->id);

        $pages = PublicSitePage::withCount('sections')
            ->where('hospital_id', $hospital->id)
            ->orderByRaw("slug = 'home' desc")
            ->orderBy('title')
            ->get();

        return Inertia::render('Admin/PublicWebsite/Index', [
            'pages' => $pages->map(fn (PublicSitePage $page) => array_merge($page->toArray(), [
                'sections_count' => $page->sections_count,
                'launch_warnings' => $this->launchWarnings($page),
            ])),
            'media' => PublicSiteMedia::where('hospital_id', $hospital->id)->latest()->paginate(8),
            'stats' => [
                'published_pages' => $pages->where('status', 'published')->count(),
                'draft_pages' => $pages->where('status', 'draft')->count(),
                'media_count' => PublicSiteMedia::where('hospital_id', $hospital->id)->count(),
                'revision_count' => PublicSiteRevision::where('hospital_id', $hospital->id)->count(),
            ],
            'can_manage_media' => $request->user()->can('website.manage_media') || $request->user()->hasRole('superadmin'),
        ]);
    }

    public function edit(PublicSitePage $page, PublicSiteMediaUsage $mediaUsage): Response
    {
        $this->authorize('view', $page);
        $mediaUsage->refreshHospital($page->hospital_id);
        $page->load(['sections.items', 'revisions.creator:id,firstname,lastname,email']);

        return Inertia::render('Admin/PublicWebsite/Edit', [
            'page' => $this->editorPagePayload($page),
            'launch_warnings' => $this->launchWarnings($page),
            'media_warnings' => $this->mediaWarnings($page),
            'homepage_sources' => $this->homepageSources($page),
            'preview_url' => URL::temporarySignedRoute('public.preview', now()->addMinutes(30), ['page' => $page]),
            'media' => PublicSiteMedia::where('hospital_id', $page->hospital_id)->latest()->get(),
            'item_types' => ['department', 'clinician', 'testimonial', 'article'],
            'can_manage_media' => request()->user()->can('website.manage_media') || request()->user()->hasRole('superadmin'),
            'can_view_json' => request()->user()->hasRole('superadmin'),
        ]);
    }

    public function updatePage(Request $request, PublicSitePage $page, AuditService $audit): RedirectResponse
    {
        $this->authorize('update', $page);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'draft_content' => ['required', 'array'],
            'seo' => ['nullable', 'array'],
            'seo.canonical_url' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['draft_content'] = $this->sanitizePayload($validated['draft_content']);
        $validated['seo'] = $this->mergeSeo($page->draft_seo ?? $page->seo ?? [], $validated['seo'] ?? []);
        $before = $page->only(['draft_title', 'draft_content', 'draft_seo']);
        $page->update([
            'draft_title' => $validated['title'],
            'draft_content' => $validated['draft_content'],
            'draft_seo' => $validated['seo'],
        ]);

        $audit->record('website.page_updated', $page, $before, $page->only(['draft_title', 'draft_content', 'draft_seo']));

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
        $before = $section->only(['draft_label', 'draft_sort_order', 'draft_is_enabled', 'draft_content']);
        $section->update([
            'draft_label' => $validated['label'],
            'draft_sort_order' => $validated['sort_order'],
            'draft_is_enabled' => $validated['is_enabled'],
            'draft_content' => $validated['draft_content'],
        ]);

        $audit->record('website.section_updated', $section, $before, $section->only(['draft_label', 'draft_sort_order', 'draft_is_enabled', 'draft_content']));

        return back()->with('success', 'Draft section saved.');
    }

    public function updateItem(Request $request, PublicSiteItem $item, AuditService $audit): RedirectResponse
    {
        $this->authorize('update', $item);

        $validated = $request->validate($this->itemRules($item));
        $this->ensureSectionBelongsToHospital($validated['public_site_section_id'] ?? null, $item->hospital_id);
        $validated['draft_content'] = $this->sanitizePayload($validated['draft_content']);
        $validated['slug'] = $this->normalizeItemSlug($validated['slug'] ?? null, $validated['title']);
        $this->ensureItemSlugIsUnique($item->hospital_id, $validated['type'], $validated['slug'], $item->id);
        $before = $item->only([
            'draft_public_site_section_id',
            'draft_type',
            'draft_slug',
            'draft_title',
            'draft_summary',
            'draft_content',
            'draft_is_enabled',
            'draft_is_featured',
            'draft_sort_order',
            'status',
        ]);
        $item->update([
            'draft_public_site_section_id' => $validated['public_site_section_id'] ?? null,
            'draft_type' => $validated['type'],
            'draft_slug' => $validated['slug'],
            'draft_title' => $validated['title'],
            'draft_summary' => $validated['summary'] ?? null,
            'draft_content' => $validated['draft_content'],
            'draft_is_enabled' => $validated['is_enabled'],
            'draft_is_featured' => $validated['is_featured'],
            'draft_sort_order' => $validated['sort_order'],
            'status' => $validated['status'],
        ]);

        $audit->record("website.{$validated['type']}_updated", $item, $before, $item->fresh()->only([
            'draft_public_site_section_id',
            'draft_type',
            'draft_slug',
            'draft_title',
            'draft_summary',
            'draft_content',
            'draft_is_enabled',
            'draft_is_featured',
            'draft_sort_order',
            'status',
        ]));

        return back()->with('success', 'Draft item saved.');
    }

    public function storeItem(Request $request, PublicSitePage $page, AuditService $audit): RedirectResponse
    {
        $this->authorize('update', $page);

        $validated = $request->validate($this->itemRules());
        $this->ensureSectionBelongsToPage($validated['public_site_section_id'] ?? null, $page);
        $validated['hospital_id'] = $page->hospital_id;
        $validated['draft_content'] = $this->sanitizePayload($validated['draft_content']);
        $validated['slug'] = $this->uniqueItemSlug($validated['hospital_id'], $validated['type'], $this->normalizeItemSlug($validated['slug'] ?? null, $validated['title']));
        $validated['status'] = 'draft';

        $item = PublicSiteItem::create([
            'hospital_id' => $validated['hospital_id'],
            'public_site_section_id' => null,
            'draft_public_site_section_id' => $validated['public_site_section_id'] ?? null,
            'type' => $validated['type'],
            'draft_type' => $validated['type'],
            'slug' => $validated['slug'],
            'draft_slug' => $validated['slug'],
            'title' => $validated['title'],
            'draft_title' => $validated['title'],
            'summary' => $validated['summary'] ?? null,
            'draft_summary' => $validated['summary'] ?? null,
            'draft_content' => $validated['draft_content'],
            'status' => 'draft',
            'is_enabled' => false,
            'draft_is_enabled' => $validated['is_enabled'],
            'is_featured' => false,
            'draft_is_featured' => $validated['is_featured'],
            'sort_order' => $validated['sort_order'],
            'draft_sort_order' => $validated['sort_order'],
        ]);
        $audit->record("website.{$item->type}_created", $item, null, $item->toArray());

        return back()->with('success', 'Draft item created.');
    }

    public function updateTheme(Request $request, PublicSitePage $page, AuditService $audit): RedirectResponse
    {
        $this->authorize('update', $page);
        abort_unless($request->user()->can('website.manage_theme'), 403);

        $validated = $request->validate([
            'appearance' => ['required', Rule::in(['light', 'dark', 'system'])],
            'accent' => ['required', Rule::in(['calm', 'healing', 'alert', 'blood', 'seagrass'])],
            'allowed_accents' => ['required', 'array', 'min:1'],
            'allowed_accents.*' => ['required', Rule::in(['calm', 'healing', 'alert', 'blood', 'seagrass'])],
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

    public function unpublishItem(PublicSiteItem $item, PublicSitePublisher $publisher): RedirectResponse
    {
        $this->authorize('unpublish', $item);
        $publisher->unpublish($item, request()->user());

        return back()->with('success', 'Item unpublished.');
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

        return back()
            ->with('success', 'Media uploaded.')
            ->with('uploaded_media', [
                'id' => $media->id,
                'title' => $media->title,
                'alt_text' => $media->alt_text,
                'caption' => $media->caption,
                'credit' => $media->credit,
                'path' => $media->path,
                'url' => $media->url,
                'mime_type' => $media->mime_type,
                'width' => $media->width,
                'height' => $media->height,
            ]);
    }

    public function deleteMedia(PublicSiteMedia $media, AuditService $audit, PublicSiteMediaUsage $mediaUsage): RedirectResponse
    {
        $mediaUsage->refreshHospital($media->hospital_id);
        $media->refresh();
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
            'type' => ['required', Rule::in(['department', 'clinician', 'testimonial', 'article'])],
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

    private function normalizeItemSlug(?string $slug, string $title): string
    {
        return Str::slug($slug ?: $title) ?: 'item';
    }

    private function uniqueItemSlug(int $hospitalId, string $type, string $slug, ?int $ignoreItemId = null): string
    {
        $candidate = $slug;
        $suffix = 2;

        while ($this->itemSlugExists($hospitalId, $type, $candidate, $ignoreItemId)) {
            $candidate = "{$slug}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    private function ensureItemSlugIsUnique(int $hospitalId, string $type, string $slug, ?int $ignoreItemId = null): void
    {
        if (! $this->itemSlugExists($hospitalId, $type, $slug, $ignoreItemId)) {
            return;
        }

        throw ValidationException::withMessages([
            'slug' => 'This slug is already used by another public website item of the same type.',
        ]);
    }

    private function itemSlugExists(int $hospitalId, string $type, string $slug, ?int $ignoreItemId = null): bool
    {
        return PublicSiteItem::query()
            ->where('hospital_id', $hospitalId)
            ->where(function ($query) use ($type, $slug): void {
                $query
                    ->where(fn ($query) => $query->where('type', $type)->where('slug', $slug))
                    ->orWhere(fn ($query) => $query->where('draft_type', $type)->where('draft_slug', $slug))
                    ->orWhere(fn ($query) => $query->where('published_type', $type)->where('published_slug', $slug));
            })
            ->when($ignoreItemId, fn ($query) => $query->whereKeyNot($ignoreItemId))
            ->exists();
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

    private function normalizeSeo(array $seo): array
    {
        if (isset($seo['canonical']) && ! isset($seo['canonical_url'])) {
            $seo['canonical_url'] = $seo['canonical'];
        }

        unset($seo['canonical']);

        return $seo;
    }

    private function mergeSeo(array $current, array $incoming): array
    {
        $current = $this->normalizeSeo($this->sanitizePayload($current));
        $incoming = $this->normalizeSeo($this->sanitizePayload($incoming));

        foreach ($incoming as $key => $value) {
            if (filled($value) || ! array_key_exists($key, $current)) {
                $current[$key] = $value;
            }
        }

        return $current;
    }

    private function editorPagePayload(PublicSitePage $page): array
    {
        return array_merge($page->toArray(), [
            'title' => $page->draft_title ?? $page->title,
            'draft_content' => $this->editorPageContent($page->draft_content ?? []),
            'draft_seo' => $this->editorSeo($page->draft_seo ?? $page->seo ?? []),
            'seo' => $this->editorSeo($page->draft_seo ?? $page->seo ?? []),
            'is_modified' => $page->draftSnapshot() !== $page->publishedSnapshot(),
            'sections' => $page->sections->map(fn (PublicSiteSection $section) => array_merge($section->toArray(), [
                'label' => $section->draft_label ?? $section->label,
                'sort_order' => $section->draft_sort_order ?? $section->sort_order,
                'is_enabled' => $section->draft_is_enabled ?? $section->is_enabled,
                'draft_content' => $this->editorSectionContent($section),
                'is_modified' => $section->draftSnapshot() !== $section->publishedSnapshot(),
                'items' => $section->items->map(fn (PublicSiteItem $item) => array_merge($item->toArray(), [
                    'public_site_section_id' => $item->draft_public_site_section_id ?? $item->public_site_section_id,
                    'type' => $item->draft_type ?? $item->type,
                    'slug' => $item->draft_slug ?? $item->slug,
                    'title' => $item->draft_title ?? $item->title,
                    'summary' => $item->draft_summary ?? $item->summary,
                    'draft_content' => $this->editorItemContent($item->draft_type ?? $item->type, $item->draft_content ?? []),
                    'is_enabled' => $item->draft_is_enabled ?? $item->is_enabled,
                    'is_featured' => $item->draft_is_featured ?? $item->is_featured,
                    'sort_order' => $item->draft_sort_order ?? $item->sort_order,
                    'is_modified' => $item->draftSnapshot() !== $item->publishedSnapshot(),
                ]))->values(),
            ]))->values(),
        ]);
    }

    private function editorPageContent(array $content): array
    {
        return array_replace_recursive([
            'utility' => [
                'visible' => false,
                'phone' => '',
                'emergency_phone' => '',
                'email' => '',
                'hours' => '',
                'location' => '',
            ],
            'navigation' => [
                'appointment_label' => 'Appointment information',
                'appointment_url' => '/appointment',
                'items' => [],
            ],
            'theme' => [
                'appearance' => 'system',
                'accent' => 'calm',
                'allowed_accents' => ['calm', 'healing', 'alert', 'blood', 'seagrass'],
                'show_switcher' => true,
            ],
            'footer' => [
                'summary' => '',
                'badges' => [],
                'copyright' => '',
            ],
        ], $content);
    }

    private function editorSeo(array $seo): array
    {
        return array_replace([
            'title' => '',
            'description' => '',
            'canonical_url' => '',
            'image' => '',
            'image_alt' => '',
        ], $this->normalizeSeo($seo));
    }

    private function editorSectionContent(PublicSiteSection $section): array
    {
        $content = $section->draft_content ?? [];

        return match ($section->key) {
            'hero' => array_replace(['rotation_ms' => 6500, 'slides' => []], $content),
            'info_banner', 'why_choose_us' => array_replace(['items' => []], $content),
            default => $content,
        };
    }

    private function editorItemContent(?string $type, array $content): array
    {
        $defaults = [
            'service' => ['icon' => '', 'description' => '', 'cta_label' => '', 'cta_url' => ''],
            'department' => ['icon' => '', 'public_title' => '', 'summary' => ''],
            'clinician' => ['display_name' => '', 'professional_title' => '', 'specialty' => '', 'bio' => '', 'photo' => '', 'alt' => ''],
            'testimonial' => ['display_name' => '', 'context' => '', 'text' => '', 'rating' => null, 'approved' => false],
            'article' => ['excerpt' => '', 'body' => '', 'image' => '', 'alt' => '', 'author' => '', 'published_on' => ''],
        ];

        return array_replace($defaults[$type] ?? [], $content);
    }

    private function launchWarnings(PublicSitePage $page): array
    {
        $page->loadMissing('hospital.settings', 'sections.items');
        $warnings = [];
        $publishedContent = $page->published_content ?? [];
        $publishedSeo = $page->published_seo ?? $page->seo ?? [];
        $textPayload = strtolower(json_encode([$publishedContent, $publishedSeo, $page->published_title, $page->title]) ?: '');
        $demoMarkers = ['demo hospital', 'example.test', 'placeholder', 'replace before production', 'demonstration content', 'sample clinician'];

        foreach ($demoMarkers as $marker) {
            if (str_contains($textPayload, $marker)) {
                $warnings[] = 'Published page content still contains demo or placeholder wording.';
                break;
            }
        }

        if ($page->status !== 'published' || ! $page->published_at) {
            $warnings[] = 'Page is not currently published.';
        }

        if (blank($publishedSeo['title'] ?? null)) {
            $warnings[] = 'SEO title is required before launch.';
        }

        if (blank($publishedSeo['description'] ?? null)) {
            $warnings[] = 'Meta description is required before launch.';
        }

        if ($page->slug === 'home') {
            $hero = $page->sections->firstWhere('key', 'hero');
            $slides = collect($hero?->published_content['slides'] ?? [])->filter(fn ($slide) => ($slide['active'] ?? true) !== false && filled($slide['headline'] ?? null));
            if ($slides->isEmpty()) {
                $warnings[] = 'Home hero needs at least one published slide with approved headline copy.';
            }

            $contact = $page->hospital->settings?->contact_details ?? [];
            if (blank($page->hospital->email) && blank($contact['public_email'] ?? null) && blank($page->hospital->phone_numbers[0] ?? null) && blank($contact['public_phone'] ?? null)) {
                $warnings[] = 'Publish verified public contact details in hospital settings before launch.';
            }

            foreach (['service' => 'service', 'clinician' => 'clinician profile', 'article' => 'news article'] as $type => $label) {
                $hasPublished = PublicSiteItem::published()->where('hospital_id', $page->hospital_id)->where('published_type', $type)->exists();
                if (! $hasPublished) {
                    $warnings[] = "No approved public {$label} is published.";
                }
            }
        }

        return array_values(array_unique($warnings));
    }

    private function homepageSources(PublicSitePage $page): array
    {
        if ($page->slug !== 'home') {
            return [];
        }

        return [
            'services' => [
                'source' => 'Service catalogue',
                'manage_url' => route('admin.services.index'),
                'public_count' => BillableService::where('hospital_id', $page->hospital_id)->where('is_active', true)->where('public_is_visible', true)->count(),
                'featured_count' => BillableService::where('hospital_id', $page->hospital_id)->where('is_active', true)->where('public_is_visible', true)->where('public_is_featured', true)->count(),
            ],
            'departments' => [
                'source' => 'Departments',
                'manage_url' => route('admin.departments.index'),
                'public_count' => Department::where('hospital_id', $page->hospital_id)->where('status', 'active')->where('public_is_visible', true)->count(),
                'featured_count' => Department::where('hospital_id', $page->hospital_id)->where('status', 'active')->where('public_is_visible', true)->where('public_is_featured', true)->count(),
            ],
            'clinicians' => [
                'source' => 'Staff profiles',
                'manage_url' => route('admin.staff.index'),
                'public_count' => StaffProfile::where('hospital_id', $page->hospital_id)->where('employment_status', 'active')->where('is_active', true)->where('public_is_visible', true)->count(),
                'featured_count' => StaffProfile::where('hospital_id', $page->hospital_id)->where('employment_status', 'active')->where('is_active', true)->where('public_is_visible', true)->where('public_is_featured', true)->count(),
            ],
        ];
    }

    private function mediaWarnings(PublicSitePage $page): array
    {
        $page->loadMissing('sections.items');
        $payloads = collect([$page->draft_content, $page->published_content, $page->draft_seo, $page->published_seo]);
        $page->sections->each(fn (PublicSiteSection $section) => $payloads->push($section->draft_content, $section->published_content));
        $page->sections->flatMap->items->each(fn (PublicSiteItem $item) => $payloads->push($item->draft_content, $item->published_content));

        return $payloads
            ->flatMap(fn ($payload) => $this->mediaReferences(is_array($payload) ? $payload : []))
            ->unique()
            ->filter(fn (string $path) => ! $this->mediaReferenceExists($path))
            ->map(fn (string $path) => "Referenced media is missing: {$path}")
            ->values()
            ->all();
    }

    private function mediaReferences(array $payload): array
    {
        $references = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                array_push($references, ...$this->mediaReferences($value));
            } elseif (is_string($value) && $this->isMediaKey((string) $key) && filled($value)) {
                $references[] = $value;
            }
        }

        return $references;
    }

    private function isMediaKey(string $key): bool
    {
        return Str::contains(Str::lower($key), ['image', 'photo', 'logo']);
    }

    private function mediaReferenceExists(string $path): bool
    {
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return true;
        }

        if (preg_match('#^/public-site/media/(\\d+)(?:/|$)#', $path, $matches)) {
            $media = PublicSiteMedia::find((int) $matches[1]);

            return $media && Storage::disk($media->disk)->exists($media->path);
        }

        if (Str::startsWith($path, '/storage/')) {
            return Storage::disk('public')->exists(Str::after($path, '/storage/'));
        }

        if (Str::startsWith($path, 'public-site/')) {
            return Storage::disk('public')->exists($path);
        }

        return file_exists(public_path(ltrim($path, '/')));
    }

    private function sanitizeText(string $value): string
    {
        $value = strip_tags($value, '<p><br><strong><em><ul><ol><li><a>');
        $value = preg_replace('/\s(on\w+|style)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $value) ?? $value;
        $value = preg_replace('/(href)\s*=\s*([\'"])\s*(javascript:|data:)[^\'"]*\2/i', 'href="#"', $value) ?? $value;

        return trim($value);
    }
}
