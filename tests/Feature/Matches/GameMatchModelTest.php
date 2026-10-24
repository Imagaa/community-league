<?php

use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\Membership;
use App\Models\MembershipSettings;
use App\Models\User;
use App\Support\Money;

// Akses awal member hanya berlaku saat saklar Membership aktif.
beforeEach(fn () => MembershipSettings::current()->update(['enabled' => true]));

it('menghitung sisa slot dari booking aktif, bukan kolom tersimpan', function () {
    $match = GameMatch::factory()->create(['capacity' => 10]);

    Booking::factory()->for($match, 'match')->create(['quantity' => 3, 'status' => 'paid']);
    Booking::factory()->for($match, 'match')->create(['quantity' => 2, 'status' => 'pending_payment']);
    Booking::factory()->for($match, 'match')->create(['quantity' => 4, 'status' => 'cancelled']);

    expect($match->fresh()->remainingSlots())->toBe(5);
});

it('menandai penuh saat sisa slot nol', function () {
    $match = GameMatch::factory()->create(['capacity' => 2]);
    Booking::factory()->for($match, 'match')->create(['quantity' => 2, 'status' => 'paid']);

    expect($match->fresh()->isFull())->toBeTrue();
});

it('bisa dibooking hanya saat computed status open', function () {
    $belumBuka = GameMatch::factory()->create([
        'status' => 'published',
        'public_opens_at' => now()->addHour(),
    ]);
    $sudahBuka = GameMatch::factory()->create([
        'status' => 'published',
        'public_opens_at' => now()->subHour(),
    ]);
    $draft = GameMatch::factory()->create([
        'status' => 'draft',
        'public_opens_at' => now()->subHour(),
    ]);

    expect($belumBuka->isBookable())->toBeFalse()
        ->and($sudahBuka->isBookable())->toBeTrue()
        ->and($draft->isBookable())->toBeFalse();
});

it('computedStatus draft dan cancelled tidak terpengaruh waktu', function () {
    $draft = GameMatch::factory()->create([
        'status' => 'draft',
        'starts_at' => now()->subWeek(),
        'ends_at' => now()->subWeek()->addHours(2),
        'public_opens_at' => now()->subWeek(),
    ]);
    $cancelled = GameMatch::factory()->create([
        'status' => 'cancelled',
        'starts_at' => now()->addWeek(),
        'public_opens_at' => now()->subHour(),
    ]);

    expect($draft->computedStatus())->toBe('draft')
        ->and($cancelled->computedStatus())->toBe('cancelled');
});

it('computedStatus published berubah jadi published/open/ongoing/finished berdasar jadwal', function () {
    $belumTerbit = GameMatch::factory()->create([
        'status' => 'published',
        'public_opens_at' => now()->addHour(),
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHours(2),
    ]);
    $sedangBuka = GameMatch::factory()->create([
        'status' => 'published',
        'public_opens_at' => now()->subHour(),
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHours(2),
    ]);
    $sedangBerlangsung = GameMatch::factory()->create([
        'status' => 'published',
        'public_opens_at' => now()->subDay(),
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);
    $sudahSelesai = GameMatch::factory()->create([
        'status' => 'published',
        'public_opens_at' => now()->subWeek(),
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subHour(),
    ]);

    expect($belumTerbit->computedStatus())->toBe('published')
        ->and($sedangBuka->computedStatus())->toBe('open')
        ->and($sedangBerlangsung->computedStatus())->toBe('ongoing')
        ->and($sudahSelesai->computedStatus())->toBe('finished');
});

it('STATUS_TRANSITIONS hanya mengizinkan draft->published, draft->cancelled, published->cancelled', function () {
    expect(GameMatch::STATUS_TRANSITIONS['draft'])->toBe(['published', 'cancelled'])
        ->and(GameMatch::STATUS_TRANSITIONS['published'])->toBe(['cancelled'])
        ->and(GameMatch::STATUS_TRANSITIONS)->not->toHaveKey('open')
        ->and(GameMatch::STATUS_TRANSITIONS)->not->toHaveKey('ongoing');
});

it('hasEarlyMemberWindow hanya true kalau member_opens_at beneran lebih awal dari public_opens_at', function () {
    $adaJendela = GameMatch::factory()->create([
        'member_opens_at' => now()->subDays(2),
        'public_opens_at' => now()->subDay(),
    ]);
    $samaWaktu = GameMatch::factory()->create([
        'member_opens_at' => now()->subDay(),
        'public_opens_at' => now()->subDay(),
    ]);

    expect($adaJendela->hasEarlyMemberWindow())->toBeTrue()
        ->and($samaWaktu->hasEarlyMemberWindow())->toBeFalse();
});

it('member aktif boleh booking di jendela akses awal, non-member tidak', function () {
    $match = GameMatch::factory()->create([
        'status' => 'published',
        'member_opens_at' => now()->subHour(),
        'public_opens_at' => now()->addHour(),
        'starts_at' => now()->addDay(),
    ]);

    $member = User::factory()->create();
    Membership::factory()->for($member)->create(['expires_at' => now()->addMonth()]);

    $nonMember = User::factory()->create();

    expect($match->isBookableBy($member))->toBeTrue()
        ->and($match->isBookableBy($nonMember))->toBeFalse()
        ->and($match->isBookableBy(null))->toBeFalse();
});

it('membership yang sudah lapsed tidak dapat akses awal', function () {
    $match = GameMatch::factory()->create([
        'status' => 'published',
        'member_opens_at' => now()->subHour(),
        'public_opens_at' => now()->addHour(),
        'starts_at' => now()->addDay(),
    ]);

    $user = User::factory()->create();
    Membership::factory()->for($user)->create(['expires_at' => now()->subDay()]);

    expect($match->isBookableBy($user))->toBeFalse();
});

it('effectiveOpensAt tetap public_opens_at kalau tidak ada jendela member', function () {
    $match = GameMatch::factory()->create([
        'member_opens_at' => now()->subHour(),
        'public_opens_at' => now()->subHour(),
    ]);
    $member = User::factory()->create();
    Membership::factory()->for($member)->create(['expires_at' => now()->addMonth()]);

    expect($match->effectiveOpensAt($member)->eq($match->public_opens_at))->toBeTrue();
});

it('memformat rupiah dengan titik ribuan tanpa desimal', function () {
    expect(Money::rupiah(35000))->toBe('Rp 35.000')
        ->and(Money::rupiah(0))->toBe('Rp 0')
        ->and(Money::rupiah(1500000))->toBe('Rp 1.500.000');
});
