<?php

use App\Models\Booking;
use App\Models\GameMatch;
use Illuminate\Support\Facades\URL;

it('menampilkan nav 5-tab (termasuk Feed) di semua halaman publik', function () {
    $match = GameMatch::factory()->create();
    $booking = Booking::factory()->for($match, 'match')->create(['user_id' => null, 'status' => 'paid']);
    $bookingUrl = URL::signedRoute('booking.show', $booking);

    $this->get(route('home'))->assertSee('Feed');
    $this->get(route('match'))->assertSee('Feed');
    $this->get(route('match.show', $match))->assertSee('Feed');
    $this->get($bookingUrl)->assertSee('Feed');
    $this->get(route('feed'))->assertSee('Feed');
    $this->get(route('membership'))->assertSee('Feed');
    $this->get(route('akun'))->assertSee('Feed');
});
