<?php

namespace App\Http\Controllers;

use App\Models\RewardItem;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketplaceController extends Controller
{
    public function index(Request $request): View
    {
        $items = RewardItem::query()
            ->where('active', true)
            ->where(fn ($q) => $q->whereNull('stock')->orWhere('stock', '>', 0))
            ->orderBy('sort_order')
            ->get();

        return view('pages.marketplace', [
            'items' => $items,
            'pointsBalance' => $request->user()->pointsBalance(),
        ]);
    }

    public function show(RewardItem $item): View
    {
        return view('pages.marketplace.show', ['item' => $item]);
    }
}
