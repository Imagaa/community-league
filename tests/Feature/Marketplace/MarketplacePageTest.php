<?php

use App\Models\RewardItem;
use App\Models\User;

it('katalog hanya menampilkan reward aktif dan tersedia', function () {
    $user = User::factory()->create();
    RewardItem::factory()->create(['name' => 'Kaos Tersedia', 'active' => true, 'stock' => 5]);
    RewardItem::factory()->create(['name' => 'Nonaktif', 'active' => false, 'stock' => 5]);
    RewardItem::factory()->create(['name' => 'Stok Habis', 'active' => true, 'stock' => 0]);

    $this->actingAs($user)->get(route('marketplace'))
        ->assertOk()
        ->assertSee('Kaos Tersedia')
        ->assertDontSee('Nonaktif')
        ->assertDontSee('Stok Habis');
});

it('katalog punya link ke halaman detail tiap item', function () {
    $user = User::factory()->create();
    $item = RewardItem::factory()->create(['name' => 'Kaos Tersedia', 'active' => true, 'stock' => 5]);

    $this->actingAs($user)->get(route('marketplace'))
        ->assertSee(route('marketplace.show', $item), false);
});
