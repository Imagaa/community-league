<?php

use App\Actions\Booking\CreateBooking;
use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\Membership;
use App\Models\MembershipSettings;
use App\Models\User;

beforeEach(fn () => MembershipSettings::current()->update(['enabled' => true]));

function tiketLunas(User $user, ?bool $memberAtPurchase): Booking
{
    return Booking::factory()->for(GameMatch::factory()->create(['points_reward' => 20]), 'match')->for($user)->create([
        'status' => 'paid', 'quantity' => 1, 'checked_in_at' => null, 'member_at_purchase' => $memberAtPurchase,
    ]);
}

it('CreateBooking mencatat status member saat tiket dibeli', function () {
    $member = User::factory()->create();
    Membership::factory()->for($member)->create(['expires_at' => now()->addMonth()]);
    $bukan = User::factory()->create();
    $match = GameMatch::factory()->create(['capacity' => 10]);

    $data = ['quantity' => 1, 'terms_version' => User::TERMS_VERSION];
    expect((new CreateBooking)->handle($match, $data + ['user_id' => $member->id])->member_at_purchase)->toBeTrue()
        ->and((new CreateBooking)->handle($match, $data + ['user_id' => $bukan->id])->member_at_purchase)->toBeFalse()
        ->and((new CreateBooking)->handle($match, $data + ['guest_name' => 'Tamu', 'guest_email' => 'tamu@contoh.id', 'guest_phone' => '081234567890'])->member_at_purchase)->toBeNull();
});

it('tiket dibeli saat member aktif tetap dapat poin walau membership sudah habis saat check-in', function () {
    $user = User::factory()->create();
    Membership::factory()->for($user)->create(['expires_at' => now()->subDay()]);

    tiketLunas($user, true)->confirmCheckin();

    expect($user->pointsBalance())->toBe(20);
});

it('tiket dibeli sebelum jadi member tidak dapat poin walau sekarang member aktif', function () {
    $user = User::factory()->create();
    Membership::factory()->for($user)->create(['expires_at' => now()->addMonth()]);

    tiketLunas($user, false)->confirmCheckin();

    expect($user->pointsBalance())->toBe(0);
});

it('booking lama (tanpa catatan) mengikuti status member saat check-in; membership dimatikan = tanpa poin', function () {
    $user = User::factory()->create();
    Membership::factory()->for($user)->create(['expires_at' => now()->addMonth()]);

    tiketLunas($user, null)->confirmCheckin();
    expect($user->pointsBalance())->toBe(20);

    MembershipSettings::current()->update(['enabled' => false]);
    tiketLunas($user, true)->confirmCheckin();
    expect($user->pointsBalance())->toBe(20);
});
