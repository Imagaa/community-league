<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\View\View;

class PostController extends Controller
{
    public function show(Post $post): View
    {
        // Hanya tipe 'news' yang punya halaman detail -- promo diarahkan ke link_url langsung
        // dari card carousel, tidak pernah nge-render halaman ini. Post nonaktif/belum terbit
        // 404 walau slug-nya ditebak/dibagikan.
        abort_unless($post->isNews(), 404);
        abort_unless($post->is_active, 404);
        abort_if($post->published_at !== null && $post->published_at->isFuture(), 404);

        return view('pages.posts.show', ['post' => $post]);
    }
}
