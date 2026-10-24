<?php

use App\Models\RewardItem;

it('isAvailable true kalau aktif dan stok cukup atau tak terbatas', function () {
    $tersedia = RewardItem::factory()->create(['active' => true, 'stock' => 5]);
    $takTerbatas = RewardItem::factory()->create(['active' => true, 'stock' => null]);
    $habis = RewardItem::factory()->create(['active' => true, 'stock' => 0]);
    $nonaktif = RewardItem::factory()->create(['active' => false, 'stock' => 5]);

    expect($tersedia->isAvailable())->toBeTrue()
        ->and($takTerbatas->isAvailable())->toBeTrue()
        ->and($habis->isAvailable())->toBeFalse()
        ->and($nonaktif->isAvailable())->toBeFalse();
});
