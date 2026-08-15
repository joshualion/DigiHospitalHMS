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
            ->where('type', 'clinician')
            ->where('slug', $slug)
            ->firstOrFail();

        return Inertia::render('Public/WebsitePage', [
            'mode' => 'published',
            'site' => $this->siteShell($hospital),
            'page' => ['slug' => 'doctor-profile', 'title' => $item->title, 'seo' => ['title' => $item->title, 'description' => $item->summary]],
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
            ->where('type', 'article')
            ->where('slug', $slug)
            ->firstOrFail();

        return Inertia::render('Public/WebsitePage', [
            'mode' => 'published',
            'site' => $this->siteShell($hospital),
            'page' => ['slug' => 'article', 'title' => $item->title, 'seo' => ['title' => $item->title, 'description' => $item->summary]],
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
        $home = PublicSitePage::where('hospital_id', $hospital->id)->where('slug', 'home')->first();
        $content = $preview ? ($home?->draft_content ?? []) : ($home?->published_content ?? []);

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
            'hospital' => $hospital->only(['display_name', 'email', 'address', 'city', 'state', 'country', 'logo_path']),
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
            'title' => $page->title,
            'template' => $page->template,
            'content' => $draft ? ($page->draft_content ?? []) : ($page->published_content ?? []),
            'seo' => $page->seo ?? [],
            'published_at' => $page->published_at?->toISOString(),
        ];
    }

    private function sectionPayloads(PublicSitePage $page, bool $draft = false): array
    {
        return $page->sections
            ->filter(fn ($section) => $draft || ($section->is_enabled && $section->published_content))
            ->mapWithKeys(fn ($section) => [$section->key => [
                'id' => $section->id,
                'key' => $section->key,
                'type' => $section->type,
                'label' => $section->label,
                'sort_order' => $section->sort_order,
                'is_enabled' => $section->is_enabled,
                'content' => $draft ? ($section->draft_content ?? []) : ($section->published_content ?? []),
            ]])
            ->all();
    }

    private function items(PublicSitePage $page, bool $draft = false): array
    {
        $items = PublicSiteItem::query()
            ->where('hospital_id', $page->hospital_id)
            ->when(! $draft, fn ($query) => $query->published())
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PublicSiteItem $item) => $this->itemPayload($item, $draft))
            ->groupBy('type');

        return $items->map(fn ($group) => $group->values())->all();
    }

    private function itemPayload(PublicSiteItem $item, bool $draft = false): array
    {
        return [
            'id' => $item->id,
            'type' => $item->type,
            'slug' => $item->slug,
            'title' => $item->title,
            'summary' => $item->summary,
            'is_featured' => $item->is_featured,
            'content' => $draft ? ($item->draft_content ?? []) : ($item->published_content ?? []),
        ];
    }
}
