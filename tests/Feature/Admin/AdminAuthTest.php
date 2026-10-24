<?php

use App\Models\Booking;
use App\Models\User;

it('mengarahkan tamu ke halaman masuk admin', function () {
    $this->get(adminUrl('/verifikasi'))->assertRedirect(adminUrl('/masuk'));
    $this->get(adminUrl('/masuk'))->assertOk()->assertSee('Masuk Admin');
});

it('menolak user biasa', function () {
    $this->actingAs(User::factory()->create())->get(adminUrl('/verifikasi'))->assertForbidden();
});

it('admin bisa masuk dengan email dan password', function () {
    buatAdmin(['email' => 'admin@example.com', 'password' => 'rahasia-123']);

    $this->post(adminUrl('/masuk'), ['email' => 'admin@example.com', 'password' => 'rahasia-123'])
        ->assertRedirect(adminUrl('/verifikasi'));

    $this->assertAuthenticated();
});

it('user biasa tidak bisa masuk lewat form admin dan pesannya sama dengan password salah', function () {
    User::factory()->create(['email' => 'user@example.com', 'password' => 'rahasia-123']);

    $this->post(adminUrl('/masuk'), ['email' => 'user@example.com', 'password' => 'rahasia-123'])
        ->assertSessionHasErrors(['email' => 'Email atau password salah.']);

    $this->assertGuest();
});

it('membatasi percobaan masuk', function () {
    foreach (range(1, 5) as $i) {
        $this->post(adminUrl('/masuk'), ['email' => 'x@example.com', 'password' => 'salah']);
    }

    $this->post(adminUrl('/masuk'), ['email' => 'x@example.com', 'password' => 'salah'])->assertStatus(429);
});

it('rute admin tidak bocor ke domain publik', function () {
    $this->get('/verifikasi')->assertNotFound();
    $this->get('/match')->assertOk()->assertDontSee('Masuk Admin');
});

it('aksi Livewire admin ditolak untuk user biasa', function () {
    $booking = Booking::factory()->create(['status' => 'awaiting_verification', 'proof_uploaded_at' => now()]);

    Livewire\Livewire::actingAs(User::factory()->create())
        ->test('pages::admin.verifikasi')
        ->assertForbidden();

    expect($booking->fresh()->status)->toBe('awaiting_verification');
});
