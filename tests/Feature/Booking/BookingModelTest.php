<?php

use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\User;

it('menghasilkan booking_code unik 8 karakter', function () {
    $a = Booking::factory()->create();
    $b = Booking::factory()->create();

    expect($a->booking_code)->toHaveLength(8)
        ->and($a->booking_code)->not->toBe($b->booking_code);
});

it('booking terhubung ke match dan opsional ke user', function () {
    $match = GameMatch::factory()->create();
    $user = User::factory()->create();

    $booking = Booking::factory()->for($match, 'match')->for($user)->create();
    $guestBooking = Booking::factory()->for($match, 'match')->create(['user_id' => null]);

    expect($booking->match->is($match))->toBeTrue()
        ->and($booking->user->is($user))->toBeTrue()
        ->and($guestBooking->user)->toBeNull();
});
