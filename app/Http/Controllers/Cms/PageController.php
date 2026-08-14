<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Pages/Index', [
            'pages' => Page::query()
                ->select(['id', 'slug', 'title', 'is_active', 'updated_at'])
                ->latest('updated_at')
                ->paginate(10),
        ]);
    }

    public function edit(Page $page): Response
    {
        $page->load('sections.blocks');

        return Inertia::render('Admin/Pages/Edit', [
            'page' => $page,
        ]);
    }

    public function update(): RedirectResponse
    {
        return back()->with('status', 'CMS editing is deferred during Phase 0.');
    }
}
