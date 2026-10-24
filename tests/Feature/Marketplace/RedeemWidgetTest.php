<?php

use App\Models\PointsTransaction;
use App\Models\RewardItem;
use App\Models\RewardRedemption;
use App\Models\User;
use Livewire\Livewire;

it('tombol tukar disabled dan tampilkan kekurangan poin kalau saldo tidak cukup', function () {
    $user = User::factory()->create();
    $item = RewardItem::factory()->create(['point_cost' => 500, 'stock' => 5]);

    Livewire::actingAs($user)
        ->test('pages::marketplace.redeem-widget', ['item' => $item])
        ->assertSee('Butuh 500 poin lagi.');
});

it('tombol tukar aktif kalau poin cukup dan stok ada', function () {
    $user = User::factory()->create();
    PointsTransaction::factory()->create(['user_id' => $user->id, 'amount' => 500, 'type' => 'admin_grant']);
    $item = RewardItem::factory()->create(['point_cost' => 500, 'stock' => 5]);

    Livewire::actingAs($user)
        ->test('pages::marketplace.redeem-widget', ['item' => $item])
        ->assertDontSee('Butuh')
        ->assertDontSee('Stok habis');
});

it('menampilkan pesan Stok habis dan disabled kalau stok kosong', function () {
    $user = User::factory()->create();
    PointsTransaction::factory()->create(['user_id' => $user->id, 'amount' => 500, 'type' => 'admin_grant']);
    $item = RewardItem::factory()->create(['point_cost' => 100, 'stock' => 0]);

    Livewire::actingAs($user)
        ->test('pages::marketplace.redeem-widget', ['item' => $item])
        ->assertSee('Stok habis.');
});

it('klik Tukar cuma buka modal konfirmasi, belum benar-benar menukar', function () {
    // Regresi ditemukan user: sebelumnya klik Tukar langsung eksekusi tanpa konfirmasi apa pun --
    // rawan tertekan tidak sengaja lalu poin langsung terpotong.
    $user = User::factory()->create();
    PointsTransaction::factory()->create(['user_id' => $user->id, 'amount' => 500, 'type' => 'admin_grant']);
    $item = RewardItem::factory()->create(['point_cost' => 200, 'stock' => 5]);

    Livewire::actingAs($user)
        ->test('pages::marketplace.redeem-widget', ['item' => $item])
        ->call('confirmRedeem')
        ->assertSet('showConfirm', true)
        ->assertSet('redeemed', false);

    expect(RewardRedemption::where('user_id', $user->id)->exists())->toBeFalse()
        ->and($user->pointsBalance())->toBe(500);
});

it('redeem sukses menampilkan pesan berhasil dan bikin redemption', function () {
    $user = User::factory()->create();
    PointsTransaction::factory()->create(['user_id' => $user->id, 'amount' => 500, 'type' => 'admin_grant']);
    $item = RewardItem::factory()->create(['point_cost' => 200, 'stock' => 5]);

    Livewire::actingAs($user)
        ->test('pages::marketplace.redeem-widget', ['item' => $item])
        ->call('redeem')
        ->assertSet('redeemed', true)
        ->assertSee('Berhasil ditukar');

    expect(RewardRedemption::where('user_id', $user->id)->where('reward_item_id', $item->id)->exists())->toBeTrue()
        ->and($user->pointsBalance())->toBe(300);
});

it('redeem gagal karena poin tidak cukup menampilkan pesan error, bukan redemption baru', function () {
    $user = User::factory()->create();
    $item = RewardItem::factory()->create(['point_cost' => 500, 'stock' => 5]);

    Livewire::actingAs($user)
        ->test('pages::marketplace.redeem-widget', ['item' => $item])
        ->call('redeem')
        ->assertHasErrors('redeem')
        ->assertSee('Poin tidak cukup');

    expect(RewardRedemption::where('user_id', $user->id)->exists())->toBeFalse();
});
