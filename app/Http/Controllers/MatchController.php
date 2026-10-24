<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\GameMatch;
use App\Models\Venue;
use App\Support\MatchOgImage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class MatchController extends Controller
{
    public function index(Request $request): View
    {
        $matches = GameMatch::query()
            ->with('venue.city')
            ->where('status', 'published')
            ->where(fn ($q) => $q->where('ends_at', '>', now())->orWhere('hide_when_finished', false))
            ->filterable($request)
            ->orderByRaw('(ends_at <= now()) asc')
            ->orderBy('starts_at')
            ->paginate(12)
            ->withQueryString();

        return view('pages.matches.index', [
            'matches' => $matches,
            'cities' => City::where('is_active', true)->orderBy('name')->get(),
            'venues' => Venue::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function show(GameMatch $match): View
    {
        // Draft belum dipublikasi -- tidak boleh terlihat publik walau link-nya ditebak/dibocorkan.
        abort_if($match->status === 'draft', 404);

        $match->load('venue.city');

        return view('pages.matches.show', ['match' => $match]);
    }

    /** Gambar kartu pratinjau link (Open Graph) -- lihat App\Support\MatchOgImage. */
    public function ogImage(GameMatch $match): Response
    {
        abort_if($match->status === 'draft', 404);

        return response(MatchOgImage::png($match), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
