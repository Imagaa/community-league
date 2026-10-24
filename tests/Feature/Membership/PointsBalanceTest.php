<?php

use App\Models\PointsTransaction;
use App\Models\User;

it('pointsBalance menjumlahkan semua transaksi user', function () {
    $user = User::factory()->create();
    PointsTransaction::factory()->create(['user_id' => $user->id, 'amount' => 100, 'type' => 'admin_grant']);
    PointsTransaction::factory()->create(['user_id' => $user->id, 'amount' => -30, 'type' => 'redemption']);
    PointsTransaction::factory()->create(['user_id' => $user->id, 'amount' => 50, 'type' => 'admin_grant']);

    expect($user->pointsBalance())->toBe(120);
});

it('pointsBalance 0 kalau belum ada transaksi', function () {
    $user = User::factory()->create();

    expect($user->pointsBalance())->toBe(0);
});

it('pointsBalance user lain tidak ikut terhitung', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    PointsTransaction::factory()->create(['user_id' => $userA->id, 'amount' => 500, 'type' => 'admin_grant']);

    expect($userB->pointsBalance())->toBe(0);
});
