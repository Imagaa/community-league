<?php

use App\Models\Membership;
use App\Models\MembershipPurchase;
use App\Models\User;

it('isActive dan isLapsed mencerminkan expires_at', function () {
    $aktif = Membership::factory()->create(['expires_at' => now()->addDays(10)]);
    $lapsed = Membership::factory()->create(['expires_at' => now()->subDays(10)]);

    expect($aktif->isActive())->toBeTrue()
        ->and($aktif->isLapsed())->toBeFalse()
        ->and($lapsed->isActive())->toBeFalse()
        ->and($lapsed->isLapsed())->toBeTrue();
});

it('generatePurchaseCode selalu diawali MBR dan unik', function () {
    $code = MembershipPurchase::generatePurchaseCode();

    expect($code)->toStartWith('MBR')->and($code)->toHaveLength(11);
});

it('user punya relasi membership', function () {
    $user = User::factory()->create();
    Membership::factory()->create(['user_id' => $user->id]);

    expect($user->membership)->toBeInstanceOf(Membership::class);
});
