<?php

use App\Models\Booking;

it('mengubah status booking yang hold-nya sudah lewat jadi expired', function () {
    $lewat = Booking::factory()->create([
        'status' => 'pending_payment',
        'hold_expires_at' => now()->subMinute(),
    ]);
    $belumLewat = Booking::factory()->create([
        'status' => 'pending_payment',
        'hold_expires_at' => now()->addMinutes(10),
    ]);

    $this->artisan('bookings:expire-holds')->assertSuccessful();

    expect($lewat->fresh()->status)->toBe('expired')
        ->and($belumLewat->fresh()->status)->toBe('pending_payment');
});

it('tidak menyentuh booking yang sudah awaiting_verification meski hold lewat', function () {
    // "hold dibekukan" saat user sudah unggah bukti -- slot tidak dilepas
    // selagi menunggu verifikasi admin, walau waktunya sudah lewat 30 menit.
    $bookingMenunggu = Booking::factory()->create([
        'status' => 'awaiting_verification',
        'hold_expires_at' => now()->subMinute(),
    ]);

    $this->artisan('bookings:expire-holds');

    expect($bookingMenunggu->fresh()->status)->toBe('awaiting_verification');
});

it('tidak menyentuh booking yang sudah paid atau expired', function () {
    $paid = Booking::factory()->create(['status' => 'paid', 'hold_expires_at' => now()->subMinute()]);
    $expired = Booking::factory()->create(['status' => 'expired', 'hold_expires_at' => now()->subMinute()]);

    $this->artisan('bookings:expire-holds');

    expect($paid->fresh()->status)->toBe('paid')
        ->and($expired->fresh()->status)->toBe('expired');
});
