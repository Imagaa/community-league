<?php

use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\Membership;
use App\Models\MembershipSettings;
use App\Models\PointsTransaction;
use App\Models\User;

// Poin check-in hanya untuk member aktif saat fitur membership dinyalakan.
beforeEach(fn () => MembershipSettings::current()->update(['enabled' => true]));

function memberAktif(): User
{
    $user = User::factory()->create();
    Membership::factory()->for($user)->create(['expires_at' => now()->addMonth()]);

    return $user;
}

it('confirmCheckin menandai checked_in_at dan memberi poin sesuai points_reward x quantity', function () {
    $user = memberAktif();
    $match = GameMatch::factory()->create(['points_reward' => 20]);
    $booking = Booking::factory()->for($match, 'match')->for($user)->create([
        'status' => 'paid',
        'quantity' => 3,
        'checked_in_at' => null,
    ]);

    $result = $booking->confirmCheckin();

    expect($result)->toBeTrue();
    expect($booking->refresh()->checked_in_at)->not->toBeNull();
    expect($user->pointsBalance())->toBe(60);

    $tx = PointsTransaction::where('user_id', $user->id)->first();
    expect($tx->type)->toBe('match_checkin')
        ->and($tx->amount)->toBe(60);
});

it('confirmCheckin dua kali tidak menggandakan poin (idempotent)', function () {
    $user = memberAktif();
    $match = GameMatch::factory()->create(['points_reward' => 20]);
    $booking = Booking::factory()->for($match, 'match')->for($user)->create([
        'status' => 'paid',
        'quantity' => 1,
        'checked_in_at' => null,
    ]);

    $first = $booking->confirmCheckin();
    $second = $booking->confirmCheckin();

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse();
    expect($user->pointsBalance())->toBe(20);
    expect(PointsTransaction::where('user_id', $user->id)->count())->toBe(1);
});

it('confirmCheckin booking tamu tetap tercatat checked_in_at tapi tidak dapat poin', function () {
    $match = GameMatch::factory()->create(['points_reward' => 20]);
    $booking = Booking::factory()->for($match, 'match')->create([
        'user_id' => null,
        'guest_email' => 'tamu@example.test',
        'status' => 'paid',
        'checked_in_at' => null,
    ]);

    $result = $booking->confirmCheckin();

    expect($result)->toBeTrue();
    expect($booking->refresh()->checked_in_at)->not->toBeNull();
    expect(PointsTransaction::count())->toBe(0);
});

it('confirmCheckin match tanpa points_reward tidak membuat PointsTransaction', function () {
    $user = memberAktif();
    $match = GameMatch::factory()->create(['points_reward' => 0]);
    $booking = Booking::factory()->for($match, 'match')->for($user)->create([
        'status' => 'paid',
        'checked_in_at' => null,
    ]);

    $booking->confirmCheckin();

    expect($booking->refresh()->checked_in_at)->not->toBeNull();
    expect(PointsTransaction::count())->toBe(0);
});

it('confirmCheckin mengirim notifikasi in-app poin masuk', function () {
    $user = memberAktif();
    $match = GameMatch::factory()->create(['points_reward' => 20, 'title' => 'Liga Malam']);
    $booking = Booking::factory()->for($match, 'match')->for($user)->create([
        'status' => 'paid',
        'quantity' => 1,
        'checked_in_at' => null,
    ]);

    $booking->confirmCheckin();

    $notification = $user->notifications()->latest()->firstOrFail();
    expect($notification->data['title'])->toBe('Poin masuk')
        ->and($notification->data['body'])->toContain('+20 poin')
        ->and($notification->data['body'])->toContain('Liga Malam');
});

it('confirmCheckin tidak kirim notifikasi kalau tidak ada poin (points_reward 0)', function () {
    $user = memberAktif();
    $match = GameMatch::factory()->create(['points_reward' => 0]);
    $booking = Booking::factory()->for($match, 'match')->for($user)->create([
        'status' => 'paid',
        'checked_in_at' => null,
    ]);

    $booking->confirmCheckin();

    expect($user->notifications()->count())->toBe(0);
});

it('confirmCheckin non-member atau saat membership dimatikan tetap hadir tapi tanpa poin', function () {
    $match = GameMatch::factory()->create(['points_reward' => 20]);
    $bukanMember = Booking::factory()->for($match, 'match')->for(User::factory()->create())->create(['status' => 'paid']);

    expect($bukanMember->confirmCheckin())->toBeTrue()
        ->and(PointsTransaction::count())->toBe(0);

    MembershipSettings::current()->update(['enabled' => false]);
    $member = Booking::factory()->for($match, 'match')->for(memberAktif())->create(['status' => 'paid']);

    expect($member->confirmCheckin())->toBeTrue()
        ->and(PointsTransaction::count())->toBe(0);
});
