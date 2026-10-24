<?php

use App\Actions\Marketplace\RedeemReward;
use App\Exceptions\RedemptionException;
use App\Models\PointsTransaction;
use App\Models\RewardItem;
use App\Models\User;

it('redeem sukses memotong poin, mengurangi stok, dan bikin redemption pending', function () {
    $user = User::factory()->create();
    PointsTransaction::factory()->create(['user_id' => $user->id, 'amount' => 500, 'type' => 'admin_grant']);
    $item = RewardItem::factory()->create(['point_cost' => 200, 'stock' => 3]);

    $redemption = (new RedeemReward)->handle($user, $item);

    expect($user->pointsBalance())->toBe(300)
        ->and($item->fresh()->stock)->toBe(2)
        ->and($redemption->status)->toBe('pending')
        ->and($redemption->points_spent)->toBe(200);
});

it('menolak redeem kalau poin tidak cukup', function () {
    $user = User::factory()->create();
    $item = RewardItem::factory()->create(['point_cost' => 200, 'stock' => 3]);

    (new RedeemReward)->handle($user, $item);
})->throws(RedemptionException::class, 'Poin tidak cukup');

it('menolak redeem kalau stok habis', function () {
    $user = User::factory()->create();
    PointsTransaction::factory()->create(['user_id' => $user->id, 'amount' => 500, 'type' => 'admin_grant']);
    $item = RewardItem::factory()->create(['point_cost' => 200, 'stock' => 0]);

    (new RedeemReward)->handle($user, $item);
})->throws(RedemptionException::class, 'Stok habis');

it('reward dengan stok tak terbatas boleh diredeem berkali-kali', function () {
    $user = User::factory()->create();
    PointsTransaction::factory()->create(['user_id' => $user->id, 'amount' => 1000, 'type' => 'admin_grant']);
    $item = RewardItem::factory()->create(['point_cost' => 100, 'stock' => null]);

    (new RedeemReward)->handle($user, $item);
    (new RedeemReward)->handle($user, $item);

    expect($item->fresh()->stock)->toBeNull()
        ->and($user->pointsBalance())->toBe(800);
});

it('dua redeem bersamaan tidak pernah membuat stok negatif', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    PointsTransaction::factory()->create(['user_id' => $userA->id, 'amount' => 500, 'type' => 'admin_grant']);
    PointsTransaction::factory()->create(['user_id' => $userB->id, 'amount' => 500, 'type' => 'admin_grant']);
    $item = RewardItem::factory()->create(['point_cost' => 100, 'stock' => 1]);

    (new RedeemReward)->handle($userA, $item);

    expect(fn () => (new RedeemReward)->handle($userB, $item))
        ->toThrow(RedemptionException::class, 'Stok habis');

    expect($item->fresh()->stock)->toBe(0);
});
