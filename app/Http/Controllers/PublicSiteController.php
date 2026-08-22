<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use App\Models\PublicSiteItem;
use App\Models\PublicSitePage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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

    public function doctor(string $slug): Response
    {
        $hospital = Hospital::primary();
        abort_unless($hospital, 404);

        $item = PublicSiteItem::published()
            ->where('hospital_id', $hospital->id)
            ->where('published_type', 'clinician')
            ->where('published_slug', $slug)
            ->firstOrFail();

        return Inertia::render('Public/WebsitePage', [
            'mode' => 'published',
            'site' => $this->siteShell($hospital),
            'page' => [
                'slug' => 'doctor-profile',
                'title' => $item->published_title ?? $item->title,
                'content' => $item->published_content ?? [],
                'seo' => ['title' => $item->published_title ?? $item->title, 'description' => $item->published_summary ?? $item->summary],
            ],
            'sections' => [],
            'items' => ['clinician' => [$this->itemPayload($item)]],
        ]);
    }

    public function article(string $slug): Response
    {
        $hospital = Hospital::primary();
        abort_unless($hospital, 404);

        $item = PublicSiteItem::published()
            ->where('hospital_id', $hospital->id)
            ->where('published_type', 'article')
            ->where('published_slug', $slug)
            ->firstOrFail();

        return Inertia::render('Public/WebsitePage', [
            'mode' => 'published',
            'site' => $this->siteShell($hospital),
            'page' => [
                'slug' => 'article',
                'title' => $item->published_title ?? $item->title,
                'content' => $item->published_content ?? [],
                'seo' => ['title' => $item->published_title ?? $item->title, 'description' => $item->published_summary ?? $item->summary],
            ],
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
            'page' => $this->pagePayload($page, true),
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
            'page' => $this->pagePayload($page),
            'sections' => $this->sectionPayloads($page),
            'items' => $this->items($page),
        ]);
    }

    private function siteShell(Hospital $hospital, bool $preview = false): array
    {
        $hospital->loadMissing('settings');
        $home = PublicSitePage::where('hospital_id', $hospital->id)->where('slug', 'home')->first();
        $content = $preview ? ($home?->draft_content ?? []) : ($home?->published_content ?? []);
        $branding = $hospital->settings?->branding ?? [];
        $tagline = $branding['tagline'] ?? $content['branding']['tagline'] ?? $content['footer']['tagline'] ?? null;

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
                ['tagline' => $tagline]
            ),
            'branding' => [
                'tagline' => $tagline,
            ],
            'utility' => $content['utility'] ?? [],
            'navigation' => $navigation,
            'footer' => $content['footer'] ?? [],
            'theme' => [
                'appearance' => in_array($theme['appearance'] ?? 'system', ['light', 'dark', 'system'], true) ? ($theme['appearance'] ?? 'system') : 'system',
                'accent' => in_array($themeAccent, $accentOptions, true) ? $themeAccent : 'calm',
                'allowedAccents' => $allowedThemeAccents ?: $accentOptions,
                'switcherVisible' => ($theme['show_switcher'] ?? true) !== false,
            ],
            'contact' => [
                'address' => trim(collect([$hospital->address, $hospital->city, $hospital->state, $hospital->country])->filter()->implode(', ')),
                'phone' => $hospital->phone_numbers[0] ?? null,
                'email' => $hospital->email,
                'hours' => $content['utility']['hours'] ?? null,
            ],
        ];
    }

    private function pagePayload(PublicSitePage $page, bool $draft = false): array
    {
        return [
            'id' => $page->id,
            'slug' => $page->slug,
            'title' => $draft ? ($page->draft_title ?? $page->title) : ($page->published_title ?? $page->title),
            'template' => $page->template,
            'content' => $draft ? ($page->draft_content ?? []) : ($page->published_content ?? []),
            'seo' => $draft ? ($page->draft_seo ?? $page->seo ?? []) : ($page->published_seo ?? $page->seo ?? []),
            'published_at' => $page->published_at?->toISOString(),
        ];
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
                'content' => $draft ? ($section->draft_content ?? []) : ($section->published_content ?? []),
            ]])
            ->all();
    }

    private function items(PublicSitePage $page, bool $draft = false): array
    {
        $items = PublicSiteItem::query()
            ->where('hospital_id', $page->hospital_id)
            ->when(! $draft, fn ($query) => $query->published())
            ->orderBy($draft ? 'draft_sort_order' : 'published_sort_order')
            ->get()
            ->map(fn (PublicSiteItem $item) => $this->itemPayload($item, $draft))
            ->groupBy('type');

        return $items->map(fn ($group) => $group->values())->all();
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
            'content' => $draft ? ($item->draft_content ?? []) : ($item->published_content ?? []),
        ];
    }
}
