<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\GameMatch;
use App\Models\Post;
use App\Models\RewardItem;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        $posts = Post::query()
            ->carouselOrder()
            ->published()
            ->limit(Post::CAROUSEL_LIMIT)
            ->get();

        $matches = GameMatch::query()
            ->with('venue.city')
            ->where('status', 'published')
            ->where(fn ($q) => $q->where('ends_at', '>', now())->orWhere('hide_when_finished', false))
            ->filterable($request)
            ->orderByRaw('(ends_at <= now()) asc')
            ->orderBy('starts_at')
            ->limit(6)
            ->get();

        $rewardItems = RewardItem::query()
            ->where('active', true)
            ->where(fn ($q) => $q->whereNull('stock')->orWhere('stock', '>', 0))
            ->orderBy('sort_order')
            ->limit(3)
            ->get();

        return view('pages.home', [
            'posts' => $posts,
            'matches' => $matches,
            'cities' => City::where('is_active', true)->orderBy('name')->get(),
            'rewardItems' => $rewardItems,
        ]);
    }
}
