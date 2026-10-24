<?php

use App\Models\MembershipBenefit;
use App\Models\MembershipSettings;

it('current mengembalikan baris tunggal yang sudah di-seed migrasi', function () {
    $settings = MembershipSettings::current();

    expect($settings->price)->toBe(100000)
        ->and($settings->duration_days)->toBe(365)
        ->and($settings->grace_period_days)->toBe(7);
});

it('benefit sudah ter-seed 4 bullet lama dan terurut sort_order', function () {
    expect(MembershipBenefit::count())->toBe(4)
        ->and(MembershipBenefit::orderBy('sort_order')->pluck('icon')->all())
        ->toBe(['tag', 'clock', 'star', 'user-group']);
});
