<?php

namespace App\Http\Controllers;

use App\Models\BlogComment;
use App\Models\Hospital;
use App\Models\PublicSiteItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BlogCommentController extends Controller
{
    public function store(Request $request, string $slug): RedirectResponse
    {
        $hospital = Hospital::primary();
        abort_unless($hospital, 404);
        $post = PublicSiteItem::published()
            ->where('hospital_id', $hospital->id)
            ->where('published_type', 'article')
            ->where('published_slug', $slug)
            ->firstOrFail();

        abort_unless(($post->published_content['comments_enabled'] ?? false) === true, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'body' => ['required', 'string', 'min:3', 'max:3000'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);
        // Honeypot submissions do not create a comment.
        if (filled($data['website'] ?? null)) {
            return back()->with('success', 'Thank you. Your comment has been received for review.');
        }

        BlogComment::create([
            'hospital_id' => $hospital->id,
            'public_site_item_id' => $post->id,
            'name' => strip_tags($data['name']),
            'email' => $data['email'],
            'body' => strip_tags($data['body']),
            'status' => 'pending',
        ]);

        return back()->with('success', 'Thank you. Your comment is awaiting moderation.');
    }
}
