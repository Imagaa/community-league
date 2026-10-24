<?php

use App\Models\GameMatch;
use App\Models\RewardItem;
use App\Models\User;
use Database\Seeders\DemoSeeder;

it('berjalan tanpa error dan menghasilkan data yang konsisten', function () {
    $this->seed(DemoSeeder::class);

    expect(User::count())->toBeGreaterThanOrEqual(12)
        ->and(GameMatch::count())->toBeGreaterThanOrEqual(15)
        ->and(RewardItem::count())->toBeGreaterThanOrEqual(11);

    $penuh = GameMatch::where('title', 'Derby Gelora Mojosongo')->firstOrFail();
    expect($penuh->remainingSlots())->toBe(0);

    $hampirPenuh = GameMatch::where('title', 'Turnamen Sore Manahan')->firstOrFail();
    expect($hampirPenuh->remainingSlots())->toBe(1);

    $lapsedHangus = User::where('email', 'fajar.nugroho@demo.communityleague.id')->firstOrFail();
    expect($lapsedHangus->pointsBalance())->toBe(0)
        ->and($lapsedHangus->membership->points_forfeited_at)->not->toBeNull();

    $lapsedTenggang = User::where('email', 'rina.marlina@demo.communityleague.id')->firstOrFail();
    expect($lapsedTenggang->pointsBalance())->toBe(400)
        ->and($lapsedTenggang->membership->points_forfeited_at)->toBeNull();
});
