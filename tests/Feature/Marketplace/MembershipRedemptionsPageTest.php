<?php

use App\Models\Membership;
use App\Models\PointsTransaction;
use App\Models\RewardRedemption;
use App\Models\User;

it('menampilkan daftar penukaran milik user dengan status dan catatan admin', function () {
    $user = User::factory()->create();
    $redemption = RewardRedemption::factory()->create([
        'user_id' => $user->id,
        'status' => 'rejected',
        'admin_note' => 'Stok fisik ternyata habis.',
    ]);

    $this->actingAs($user)
        ->get(route('membership.redemptions'))
        ->assertOk()
        ->assertSee($redemption->rewardItem->name)
        ->assertSee('Ditolak')
        ->assertSee('Stok fisik ternyata habis.');
});

it('menampilkan riwayat perolehan poin (transaksi bernilai positif) milik user', function () {
    $user = User::factory()->create();
    PointsTransaction::factory()->create([
        'user_id' => $user->id,
        'amount' => 25,
        'type' => 'match_checkin',
        'description' => 'Check-in Liga Malam Nusukan',
    ]);
    // Transaksi negatif (penukaran) TIDAK boleh nyasar masuk daftar "Perolehan Poin".
    PointsTransaction::factory()->create(['user_id' => $user->id, 'amount' => -100, 'type' => 'redemption']);

    $response = $this->actingAs($user)->get(route('membership.redemptions'))->assertOk();

    $response->assertSee('Perolehan Poin')
        ->assertSee('Check-in Liga Malam Nusukan')
        ->assertSee('+25 poin');
});

it('tidak menampilkan penukaran milik user lain', function () {
    $user = User::factory()->create();
    $lain = User::factory()->create();
    RewardRedemption::factory()->create(['user_id' => $lain->id, 'status' => 'pending']);

    $this->actingAs($user)
        ->get(route('membership.redemptions'))
        ->assertOk()
        ->assertSee('Belum ada penukaran poin.');
});

it('tamu tanpa login tidak bisa akses halaman penukaran', function () {
    $this->get(route('membership.redemptions'))->assertRedirect(route('login'));
});

it('halaman membership aktif punya link ke riwayat penukaran', function () {
    $user = User::factory()->create();
    Membership::factory()->for($user)->create(['expires_at' => now()->addMonth()]);

    $this->actingAs($user)
        ->get(route('membership'))
        ->assertSee(route('membership.redemptions'), false);
});
