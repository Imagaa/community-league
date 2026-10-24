<?php

use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\MembershipPurchase;
use App\Models\PointsTransaction;
use App\Models\RewardItem;
use App\Models\RewardRedemption;
use App\Models\User;

it('riwayat menggabungkan booking, mutasi poin, pembayaran membership, dan redemption', function () {
    $user = User::factory()->create();
    $match = GameMatch::factory()->create(['title' => 'Match Riwayat Test']);

    Booking::factory()->for($match, 'match')->for($user)->create(['status' => 'paid', 'paid_at' => now()->subDays(3)]);
    PointsTransaction::factory()->for($user)->create(['amount' => 50, 'description' => 'Bonus poin test', 'created_at' => now()->subDays(2)]);
    MembershipPurchase::factory()->for($user)->create(['status' => 'paid', 'paid_at' => now()->subDay()]);
    $item = RewardItem::factory()->create(['name' => 'Kaos Test']);
    RewardRedemption::factory()->for($user)->for($item, 'rewardItem')->create(['redeemed_at' => now()]);

    $this->actingAs($user)
        ->get(route('akun.riwayat'))
        ->assertOk()
        ->assertSee('Match Riwayat Test')
        ->assertSee('Bonus poin test')
        ->assertSee('Pembayaran Membership')
        ->assertSee('Kaos Test');
});

it('riwayat kosong menampilkan pesan yang sesuai', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('akun.riwayat'))
        ->assertSee('Belum ada riwayat transaksi.');
});
