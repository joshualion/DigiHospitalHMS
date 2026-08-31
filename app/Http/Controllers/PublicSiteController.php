<?php

namespace App\Http\Controllers;

use App\Models\BillableService;
use App\Models\Department;
use App\Models\Hospital;
use App\Models\PublicSiteItem;
use App\Models\PublicSiteMedia;
use App\Models\PublicSitePage;
use App\Models\StaffProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicSiteController extends Controller
{
    public function home(Request $request): Response
    {
        return $this->renderPage('home', $request);
    }

    public function page(string $slug, Request $request): Response
    {
        return $this->renderPage($slug, $request);
    }

    public function doctor(string $slug, Request $request): Response
    {
        $hospital = Hospital::primary();
        abort_unless($hospital, 404);

        $profile = StaffProfile::with(['user:id,firstname,lastname,status', 'hospital:id,display_name'])
            ->where('hospital_id', $hospital->id)
            ->where('public_is_visible', true)
            ->where('public_slug', $slug)
            ->where('is_active', true)
            ->where('employment_status', 'active')
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->where(fn ($query) => $this->qualifiedClinicianScope($query))
            ->first();

        if ($profile) {
            $item = $this->clinicianPayload($profile);
            $page = [
                'slug' => 'doctor-profile',
                'title' => $item['title'],
                'content' => $item['content'],
                'seo' => ['title' => $item['title'], 'description' => $item['summary']],
            ];
            $page['seo'] = $this->seoPayload($hospital, $page, $request, $page['content']['photo'] ?? null);

            return Inertia::render('Public/WebsitePage', [
                'mode' => 'published',
                'site' => $this->siteShell($hospital),
                'page' => $page,
                'sections' => [],
                'items' => ['clinician' => [$item]],
            ]);
        }

        $item = PublicSiteItem::published()
            ->where('hospital_id', $hospital->id)
            ->where('published_type', 'clinician')
            ->where('published_slug', $slug)
            ->firstOrFail();

        $page = [
            'slug' => 'doctor-profile',
            'title' => $item->published_title ?? $item->title,
            'content' => $this->normalizeContentImages($item->published_content ?? []),
            'seo' => ['title' => $item->published_title ?? $item->title, 'description' => $item->published_summary ?? $item->summary],
        ];

        $page['seo'] = $this->seoPayload($hospital, $page, $request, $page['content']['photo'] ?? $page['content']['image'] ?? null);

        return Inertia::render('Public/WebsitePage', [
            'mode' => 'published',
            'site' => $this->siteShell($hospital),
            'page' => $page,
            'sections' => [],
            'items' => ['clinician' => [$this->itemPayload($item)]],
        ]);
    }

    public function article(string $slug, Request $request): Response
    {
        $hospital = Hospital::primary();
        abort_unless($hospital, 404);

        $item = PublicSiteItem::published()
            ->where('hospital_id', $hospital->id)
            ->where('published_type', 'article')
            ->where('published_slug', $slug)
            ->firstOrFail();

        $page = [
            'slug' => 'article',
            'title' => $item->published_title ?? $item->title,
            'content' => $this->normalizeContentImages($item->published_content ?? []),
            'seo' => ['title' => $item->published_title ?? $item->title, 'description' => $item->published_summary ?? $item->summary],
            'published_at' => $item->published_at?->toISOString(),
        ];

        $page['seo'] = $this->seoPayload($hospital, $page, $request, $page['content']['image'] ?? null);

        return Inertia::render('Public/WebsitePage', [
            'mode' => 'published',
            'site' => $this->siteShell($hospital),
            'page' => $page,
            'sections' => [],
            'items' => ['article' => [$this->itemPayload($item)]],
        ]);
    }

    public function preview(PublicSitePage $page, Request $request): Response
    {
        abort_unless($request->hasValidSignature() || $request->user()?->can('preview', $page), 403);

        $page->load('sections.items');

        return Inertia::render('Public/WebsitePage', [
            'mode' => 'preview',
            'site' => $this->siteShell($page->hospital, true),
            'page' => $this->pagePayload($page, $request, true),
            'sections' => $this->sectionPayloads($page, true),
            'items' => $this->items($page, true),
        ]);
    }

    private function renderPage(string $slug, Request $request): Response
    {
        $hospital = Hospital::primary();
        abort_unless($hospital, 404);

        $page = PublicSitePage::with('sections.items')
            ->where('hospital_id', $hospital->id)
            ->where('slug', $slug)
            ->published()
            ->firstOrFail();

        return Inertia::render('Public/WebsitePage', [
            'mode' => 'published',
            'site' => $this->siteShell($hospital),
            'page' => $this->pagePayload($page, $request),
            'sections' => $this->sectionPayloads($page),
            'items' => $this->items($page),
        ]);
    }

    public function media(PublicSiteMedia $media, ?string $filename = null): StreamedResponse
    {
        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);

        return Storage::disk($media->disk)->response($media->path, $filename ?: basename($media->path), [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    public function siteShell(Hospital $hospital, bool $preview = false): array
    {
        $hospital->loadMissing('settings');
        $home = PublicSitePage::where('hospital_id', $hospital->id)->where('slug', 'home')->first();
        $content = $preview ? ($home?->draft_content ?? []) : ($home?->published_content ?? []);
        $contactSection = $home?->sections()->where('key', 'contact')->first();
        $contactSectionContent = $contactSection ? ($preview ? ($contactSection->draft_content ?? []) : ($contactSection->published_content ?? [])) : [];
        $branding = $hospital->settings?->branding ?? [];
        $contactDetails = $hospital->settings?->contact_details ?? [];
        $tagline = $branding['tagline'] ?? $content['branding']['tagline'] ?? $content['footer']['tagline'] ?? null;
        $hospitalPhone = $this->publicPhone($hospital->phone_numbers[0] ?? null);

        $theme = $content['theme'] ?? [];
        $accentMap = [
            'calm-blue' => 'calm',
            'healing-green' => 'healing',
            'warm-gold' => 'alert',
            'vital-red' => 'blood',
        ];
        $accentOptions = ['calm', 'healing', 'alert', 'blood', 'seagrass'];
        $themeAccent = $accentMap[$theme['accent'] ?? ''] ?? ($theme['accent'] ?? 'calm');
        $allowedThemeAccents = collect($theme['allowed_accents'] ?? $accentOptions)
            ->map(fn (string $accent) => $accentMap[$accent] ?? $accent)
            ->intersect($accentOptions)
            ->values()
            ->all();
        $navigation = collect($content['navigation']['items'] ?? [])
            ->map(fn (array $item) => [
                'label' => $item['label'] ?? 'Link',
                'href' => $item['href'] ?? $item['url'] ?? '/',
            ])
            ->values()
            ->all();

        return [
            'hospital' => array_merge(
                $hospital->only(['display_name', 'email', 'address', 'city', 'state', 'country', 'logo_path']),
                [
                    'tagline' => $tagline,
                    'logo_url' => $this->publicUrl($hospital->logo_path),
                ]
            ),
            'branding' => [
                'tagline' => $tagline,
            ],
            'utility' => $content['utility'] ?? [],
            'navigation' => $navigation,
            'footer' => $this->normalizeContentImages($content['footer'] ?? []),
            'theme' => [
                'appearance' => in_array($theme['appearance'] ?? 'system', ['light', 'dark', 'system'], true) ? ($theme['appearance'] ?? 'system') : 'system',
                'accent' => in_array($themeAccent, $accentOptions, true) ? $themeAccent : 'calm',
                'allowedAccents' => $allowedThemeAccents ?: $accentOptions,
                'switcherVisible' => ($theme['show_switcher'] ?? true) !== false,
            ],
            'contact' => [
                'address' => $contactDetails['public_address'] ?? $contactSectionContent['address'] ?? trim(collect([$hospital->address, $hospital->city, $hospital->state, $hospital->country])->filter()->implode(', ')),
                'phone' => $this->publicPhone($contactDetails['public_phone'] ?? $contactSectionContent['phone'] ?? $content['utility']['phone'] ?? $content['utility']['emergency_phone'] ?? $hospitalPhone),
                'email' => $contactDetails['public_email'] ?? $contactSectionContent['email'] ?? $content['utility']['email'] ?? $hospital->email,
                'hours' => $contactSectionContent['hours'] ?? $content['utility']['hours'] ?? null,
            ],
            'fallbacks' => [
                'logo' => asset('logo.jpg'),
                'image' => asset('no_img.jpg'),
                'social_image' => asset('logo.jpg'),
            ],
        ];
    }

    private function pagePayload(PublicSitePage $page, Request $request, bool $draft = false): array
    {
        $payload = [
            'id' => $page->id,
            'slug' => $page->slug,
            'title' => $draft ? ($page->draft_title ?? $page->title) : ($page->published_title ?? $page->title),
            'template' => $page->template,
            'content' => $this->normalizeContentImages($draft ? ($page->draft_content ?? []) : ($page->published_content ?? [])),
            'seo' => $draft ? ($page->draft_seo ?? $page->seo ?? []) : ($page->published_seo ?? $page->seo ?? []),
            'published_at' => $page->published_at?->toISOString(),
        ];

        $payload['seo'] = $this->seoPayload($page->hospital, $payload, $request, $payload['content']['image'] ?? null, $draft);

        return $payload;
    }

    private function sectionPayloads(PublicSitePage $page, bool $draft = false): array
    {
        return $page->sections
            ->filter(fn ($section) => $draft || ($section->published_is_enabled && $section->published_content))
            ->sortBy(fn ($section) => $draft ? ($section->draft_sort_order ?? $section->sort_order) : ($section->published_sort_order ?? $section->sort_order))
            ->mapWithKeys(fn ($section) => [$section->key => [
                'id' => $section->id,
                'key' => $section->key,
                'type' => $section->type,
                'label' => $draft ? ($section->draft_label ?? $section->label) : ($section->published_label ?? $section->label),
                'sort_order' => $draft ? ($section->draft_sort_order ?? $section->sort_order) : ($section->published_sort_order ?? $section->sort_order),
                'is_enabled' => $draft ? ($section->draft_is_enabled ?? $section->is_enabled) : ($section->published_is_enabled ?? $section->is_enabled),
                'content' => $this->normalizeContentImages($draft ? ($section->draft_content ?? []) : ($section->published_content ?? [])),
            ]])
            ->all();
    }

    private function items(PublicSitePage $page, bool $draft = false): array
    {
        $cmsItems = PublicSiteItem::query()
            ->where('hospital_id', $page->hospital_id)
            ->whereIn($draft ? 'draft_type' : 'published_type', ['testimonial', 'article'])
            ->when(! $draft, fn ($query) => $query->published())
            ->orderBy($draft ? 'draft_sort_order' : 'published_sort_order')
            ->get()
            ->map(fn (PublicSiteItem $item) => $this->itemPayload($item, $draft))
            ->groupBy('type')
            ->map(fn ($group) => $group->values()->all())
            ->all();

        return array_replace($cmsItems, [
            'service' => $this->servicePayloads($page),
            'department' => $this->departmentPayloads($page),
            'clinician' => $this->clinicianPayloads($page),
        ]);
    }

    private function servicePayloads(PublicSitePage $page): array
    {
        return BillableService::with('department:id,name')
            ->where('hospital_id', $page->hospital_id)
            ->where('is_active', true)
            ->where('public_is_visible', true)
            ->when($page->slug === 'home', fn ($query) => $query->where('public_is_featured', true))
            ->orderBy('public_display_order')
            ->orderBy('name')
            ->get()
            ->map(fn (BillableService $service) => [
                'id' => $service->id,
                'type' => 'service',
                'slug' => $service->public_slug ?: Str::slug($service->public_name ?: $service->name),
                'title' => $service->public_name ?: $service->name,
                'summary' => $service->public_description ?: $service->description,
                'is_featured' => $service->public_is_featured,
                'source' => 'billable_service',
                'content' => $this->normalizeContentImages([
                    'icon' => $service->public_icon ?: 'stethoscope',
                    'description' => $service->public_description ?: $service->description,
                    'image' => $service->public_image_path,
                    'department' => $service->department?->name,
                    'cta_label' => 'Learn more',
                    'cta_url' => '/services',
                ]),
            ])
            ->values()
            ->all();
    }

    private function departmentPayloads(PublicSitePage $page): array
    {
        return Department::query()
            ->where('hospital_id', $page->hospital_id)
            ->where('status', 'active')
            ->where('public_is_visible', true)
            ->when($page->slug === 'home', fn ($query) => $query->where('public_is_featured', true))
            ->orderBy('public_display_order')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department) => [
                'id' => $department->id,
                'type' => 'department',
                'slug' => $department->public_slug ?: Str::slug($department->public_name ?: $department->name),
                'title' => $department->public_name ?: $department->name,
                'summary' => $department->public_description ?: $department->description,
                'is_featured' => $department->public_is_featured,
                'source' => 'department',
                'content' => $this->normalizeContentImages([
                    'icon' => $department->public_icon ?: 'building-2',
                    'summary' => $department->public_description ?: $department->description,
                    'image' => $department->public_image_path,
                ]),
            ])
            ->values()
            ->all();
    }

    private function clinicianPayloads(PublicSitePage $page): array
    {
        return StaffProfile::with(['user:id,firstname,lastname,status'])
            ->where('hospital_id', $page->hospital_id)
            ->where('is_active', true)
            ->where('employment_status', 'active')
            ->where('public_is_visible', true)
            ->when($page->slug === 'home', fn ($query) => $query->where('public_is_featured', true))
            ->whereHas('user', fn ($query) => $query->where('status', 'active'))
            ->where(fn ($query) => $this->qualifiedClinicianScope($query))
            ->orderBy('public_display_order')
            ->orderBy('id')
            ->get()
            ->map(fn (StaffProfile $profile) => $this->clinicianPayload($profile))
            ->values()
            ->all();
    }

    private function clinicianPayload(StaffProfile $profile): array
    {
        $name = $profile->public_display_name ?: $profile->user?->full_name ?: 'Clinician';

        return [
            'id' => $profile->id,
            'type' => 'clinician',
            'slug' => $profile->public_slug ?: Str::slug($name),
            'title' => $name,
            'summary' => $profile->public_specialty ?: $profile->job_title,
            'is_featured' => $profile->public_is_featured,
            'source' => 'staff_profile',
            'content' => $this->normalizeContentImages([
                'display_name' => $name,
                'professional_title' => $profile->public_specialty ?: $profile->job_title,
                'specialty' => $profile->public_specialty,
                'bio' => $profile->public_summary,
                'photo' => $profile->public_photo_path,
                'alt' => $profile->public_photo_alt ?: "{$name} profile photograph",
            ]),
        ];
    }

    private function qualifiedClinicianScope($query): void
    {
        $query->whereIn('staff_category', ['clinical', 'doctor', 'nurse'])
            ->orWhere('job_title', 'like', '%doctor%')
            ->orWhere('job_title', 'like', '%clinician%')
            ->orWhereHas('user.roles', fn ($roles) => $roles->whereIn('name', ['doctor', 'nurse', 'laboratory-scientist', 'radiology-staff', 'pharmacist']));
    }

    private function itemPayload(PublicSiteItem $item, bool $draft = false): array
    {
        return [
            'id' => $item->id,
            'type' => $draft ? ($item->draft_type ?? $item->type) : ($item->published_type ?? $item->type),
            'slug' => $draft ? ($item->draft_slug ?? $item->slug) : ($item->published_slug ?? $item->slug),
            'title' => $draft ? ($item->draft_title ?? $item->title) : ($item->published_title ?? $item->title),
            'summary' => $draft ? ($item->draft_summary ?? $item->summary) : ($item->published_summary ?? $item->summary),
            'is_featured' => $draft ? ($item->draft_is_featured ?? $item->is_featured) : ($item->published_is_featured ?? $item->is_featured),
            'content' => $this->normalizeContentImages($draft ? ($item->draft_content ?? []) : ($item->published_content ?? [])),
        ];
    }

    private function seoPayload(Hospital $hospital, array $page, Request $request, ?string $image = null, bool $preview = false): array
    {
        $seo = $page['seo'] ?? [];
        $title = trim((string) ($seo['title'] ?? $page['title'] ?? $hospital->display_name));
        $description = trim((string) ($seo['description'] ?? Arr::get($hospital->settings?->public_site_defaults ?? [], 'description', '')));
        $canonical = $preview ? null : $this->absoluteUrl($seo['canonical_url'] ?? $request->path());
        $socialImage = $this->absoluteUrl($seo['image'] ?? $image ?? $hospital->logo_path ?? 'logo.jpg');

        return array_filter([
            'title' => $title,
            'description' => $description,
            'canonical_url' => $canonical,
            'image' => $socialImage,
            'image_alt' => $seo['image_alt'] ?? $hospital->display_name,
            'og_type' => in_array($page['slug'] ?? '', ['article'], true) ? 'article' : 'website',
            'twitter_card' => 'summary_large_image',
            'robots' => $preview ? 'noindex,nofollow' : ($seo['robots'] ?? null),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function normalizeContentImages(array $content): array
    {
        foreach ($content as $key => $value) {
            if (is_array($value)) {
                $content[$key] = $this->normalizeContentImages($value);

                continue;
            }

            if (is_string($value) && $this->isMediaKey((string) $key)) {
                $content[$key] = $this->publicUrl($value);
            }
        }

        return $content;
    }

    private function isMediaKey(string $key): bool
    {
        return Str::contains(Str::lower($key), ['image', 'photo', 'logo']);
    }

    private function publicUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if ($media = $this->mediaFromReference($path)) {
            return $media->url;
        }

        if (filter_var($path, FILTER_VALIDATE_URL) || Str::startsWith($path, '/')) {
            return $path;
        }

        return asset($path);
    }

    private function absoluteUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if ($media = $this->mediaFromReference($path)) {
            return url($media->url);
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return url(Str::startsWith($path, '/') ? $path : "/{$path}");
    }

    private function mediaFromReference(string $path): ?PublicSiteMedia
    {
        if (preg_match('#^/public-site/media/(\d+)(?:/|$)#', $path, $matches)) {
            return PublicSiteMedia::find((int) $matches[1]);
        }

        if (Str::startsWith($path, '/storage/public-site/')) {
            return PublicSiteMedia::where('path', Str::after($path, '/storage/'))->first();
        }

        if (Str::startsWith($path, 'public-site/')) {
            return PublicSiteMedia::where('path', $path)->first();
        }

        return null;
    }

    private function publicPhone(?string $phone): ?string
    {
        $normalized = preg_replace('/[^0-9+]/', '', (string) $phone);

        return in_array($normalized, ['', '+2340000000000', '2340000000000'], true) ? null : $phone;
    }
}
