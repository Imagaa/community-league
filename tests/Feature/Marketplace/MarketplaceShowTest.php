<?php

use App\Models\RewardItem;
use App\Models\User;

it('menampilkan detail merchandise', function () {
    $user = User::factory()->create();
    $item = RewardItem::factory()->create([
        'name' => 'Kaos Community League',
        'description' => 'Kaos edisi khusus',
        'point_cost' => 500,
    ]);

    $this->actingAs($user)
        ->get(route('marketplace.show', $item))
        ->assertOk()
        ->assertSee('Kaos Community League')
        ->assertSee('Kaos edisi khusus')
        ->assertSee('500');
});

it('item yang tidak ada mengembalikan 404', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/marketplace/99999')->assertNotFound();
});
