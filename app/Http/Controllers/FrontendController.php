<?php

namespace App\Http\Controllers;

use App\Models\Page;

class FrontendController extends Controller
{
    public function home()
    {
        $page = Page::with('sections.blocks')
            ->where('slug', 'home')
            ->firstOrFail();

        return view('frontend.home', compact('page'));
    }
}
