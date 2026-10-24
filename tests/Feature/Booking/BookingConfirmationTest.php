<?php

use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Support\Facades\URL;

it('user pemilik booking bisa membuka halaman konfirmasi', function () {
    $user = User::factory()->create();
    $match = GameMatch::factory()->create(['title' => 'Match Konfirmasi']);
    $booking = Booking::factory()->for($match, 'match')->for($user)->create(['status' => 'paid']);

    $this->actingAs($user)
        ->get(route('booking.show', $booking))
        ->assertOk()
        ->assertSee('Match Konfirmasi')
        ->assertSee($booking->booking_code);
});

it('user lain tidak bisa membuka booking bukan miliknya', function () {
    $pemilik = User::factory()->create();
    $userLain = User::factory()->create();
    $booking = Booking::factory()->for($pemilik)->create();

    $this->actingAs($userLain)
        ->get(route('booking.show', $booking))
        ->assertForbidden();
});

it('tamu belum login ditolak membuka booking user tanpa tanda tangan URL', function () {
    $pemilik = User::factory()->create();
    $booking = Booking::factory()->for($pemilik)->create();

    $this->get(route('booking.show', $booking))->assertForbidden();
});

it('guest bisa membuka booking miliknya lewat tautan bertanda tangan', function () {
    $booking = Booking::factory()->create([
        'user_id' => null,
        'guest_name' => 'Budi',
        'guest_email' => 'budi@example.test',
        'guest_phone' => '081234567890',
        'status' => 'paid',
    ]);

    $signedUrl = URL::signedRoute('booking.show', $booking);

    $this->get($signedUrl)->assertOk()->assertSee('Budi');
});

it('guest ditolak membuka booking guest lain tanpa tanda tangan yang benar', function () {
    $bookingA = Booking::factory()->create(['user_id' => null]);
    $bookingB = Booking::factory()->create(['user_id' => null]);

    // Tanda tangan sah untuk booking A dipakai mencoba membuka booking B -- harus gagal.
    $signedUrlUntukA = URL::signedRoute('booking.show', $bookingA);
    $urlUntukBDenganTandaTanganA = str_replace((string) $bookingA->id, (string) $bookingB->id, $signedUrlUntukA);

    $this->get($urlUntukBDenganTandaTanganA)->assertForbidden();
});
