<?php

namespace Tests\Feature\Models;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingUrlForTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_tamu_menghasilkan_url_bertanda_tangan(): void
    {
        $booking = Booking::factory()->create(['user_id' => null, 'guest_email' => 'tamu@example.test']);

        $url = $booking->urlFor('booking.show');

        $this->assertStringContainsString('signature=', $url);
    }

    public function test_booking_user_login_menghasilkan_url_polos(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id]);

        $url = $booking->urlFor('booking.show');

        $this->assertStringNotContainsString('signature=', $url);
    }
}
