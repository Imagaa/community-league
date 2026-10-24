<?php

use App\Models\RewardItem;
use Database\Seeders\MerchandiseSeeder;
use Illuminate\Support\Facades\Storage;

it('membuat 9 reward item dengan foto asli tersimpan di disk public', function () {
    Storage::fake('public');

    $this->seed(MerchandiseSeeder::class);

    expect(RewardItem::count())->toBe(9);

    $kaos = RewardItem::where('name', 'Kaos Community League')->firstOrFail();
    expect($kaos->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($kaos->image_path);
    expect(str_ends_with($kaos->image_path, '.webp'))->toBeTrue();

    $lanyard = RewardItem::where('name', 'Lanyard Community League')->firstOrFail();
    expect($lanyard->point_cost)->toBe(150);

    expect(RewardItem::where('name', 'like', 'Jersey Community League%')->count())->toBe(3);
});

it('idempotent -- dijalankan dua kali tidak menggandakan reward item', function () {
    Storage::fake('public');

    $this->seed(MerchandiseSeeder::class);
    $this->seed(MerchandiseSeeder::class);

    expect(RewardItem::count())->toBe(9);
});

it('memperbarui image_path item yang sudah ada, bukan bikin baris baru', function () {
    Storage::fake('public');

    $this->seed(MerchandiseSeeder::class);
    $before = RewardItem::where('name', 'Kaos Community League')->firstOrFail();

    $this->seed(MerchandiseSeeder::class);
    $after = RewardItem::where('name', 'Kaos Community League')->firstOrFail();

    expect($after->id)->toBe($before->id)
        ->and($after->image_path)->not->toBe($before->image_path);
});
