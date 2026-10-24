<?php

use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('user login bisa booking tanpa mengisi ulang data diri', function () {
    $match = GameMatch::factory()->create();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('booking.store', $match), [
        'quantity' => 1,
        'terms' => '1',
    ]);

    $booking = Booking::first();
    $response->assertRedirect(route('booking.payment', $booking));
    expect($booking->user_id)->toBe($user->id);
});

it('guest wajib mengisi nama, email, nomor HP, dan menyetujui S&K', function () {
    $match = GameMatch::factory()->create();

    $this->post(route('booking.store', $match), [
        'quantity' => 1,
    ])->assertSessionHasErrors(['guest_name', 'guest_email', 'guest_phone', 'terms']);

    expect(Booking::count())->toBe(0);
});

it('guest lengkap berhasil booking', function () {
    $match = GameMatch::factory()->create();

    $response = $this->post(route('booking.store', $match), [
        'guest_name' => 'Budi Santoso',
        'guest_email' => 'budi@example.test',
        'guest_phone' => '081234567890',
        'quantity' => 1,
        'terms' => '1',
    ]);

    $booking = Booking::first();
    $response->assertRedirectToSignedRoute('booking.payment', $booking);
    expect($booking->isGuest())->toBeTrue()
        ->and($booking->quantity)->toBe(1);
});

it('alur guest utuh: submit lalu ikuti redirect sampai halaman konfirmasi terbuka', function () {
    Http::fake([
        'api-pay.sumopod.com/*' => Http::response(['payment_id' => 'p', 'payment_link_url' => 'https://pay.sumopod.com/pay/p']),
    ]);

    $match = GameMatch::factory()->create();

    $response = $this->post(route('booking.store', $match), [
        'guest_name' => 'Budi Santoso',
        'guest_email' => 'budi@example.test',
        'guest_phone' => '081234567890',
        'quantity' => 1,
        'terms' => '1',
    ]);

    $booking = Booking::firstOrFail();

    // Buka URL yang sungguh-sungguh dikirim controller (bukan URL yang dirakit ulang di test).
    $this->get($response->headers->get('Location'))
        ->assertOk()
        ->assertSee($booking->booking_code);
});

it('kuantitas di luar 1-1 ditolak validasi', function () {
    $match = GameMatch::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('booking.store', $match), [
        'quantity' => 2,
        'terms' => '1',
    ])->assertSessionHasErrors('quantity');
});

it('pesan error dari CreateBooking ditampilkan balik ke form', function () {
    $match = GameMatch::factory()->create(['capacity' => 1]);
    Booking::factory()->for($match, 'match')->create(['quantity' => 1, 'status' => 'paid']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('booking.store', $match), [
        'quantity' => 1,
        'terms' => '1',
    ]);

    $response->assertSessionHasErrors('booking');
});
