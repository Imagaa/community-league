<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_pending_payment_redirect_ke_halaman_pembayaran(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'pending_payment']);

        $this->actingAs($user)
            ->get(route('booking.show', $booking))
            ->assertRedirect(route('booking.payment', $booking));
    }

    public function test_booking_lunas_menampilkan_kode_booking_dan_qr_checkin(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'paid']);
        $booking->load('match.venue');

        $this->actingAs($user)
            ->get(route('booking.show', $booking))
            ->assertOk()
            ->assertSee($booking->booking_code)
            ->assertSee('Download Tiket')
            ->assertSee('data:image/png;base64,', false);
    }

    public function test_booking_tamu_lunas_tidak_menampilkan_tautan_kembali_tiket_saya(): void
    {
        $booking = Booking::factory()->create(['user_id' => null, 'guest_email' => 'tamu@example.test', 'status' => 'paid']);

        $this->get($booking->urlFor('booking.show'))
            ->assertOk()
            ->assertDontSee('Tiket Saya');
    }

    public function test_booking_kedaluwarsa_menampilkan_pesan_tidak_berlaku(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'expired']);

        $this->actingAs($user)
            ->get(route('booking.show', $booking))
            ->assertOk()
            ->assertSee('tidak berlaku');
    }
}
