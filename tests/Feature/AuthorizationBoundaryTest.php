<?php

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\URL;

/*
 * Harness batas otorisasi.
 *
 * Setiap rencana berikutnya WAJIB menambahkan kasus di sini untuk model barunya:
 *   1B → booking milik user lain
 *   1D → wallet dan mutasi saldo user lain
 *   1E → poin dan pengajuan penukaran hadiah user lain
 *
 * Aturannya satu: user A tidak boleh pernah melihat atau mengubah data user B.
 */

it('menolak user membuka halaman profil user lain', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    $this->actingAs($a)
        ->get(route('akun.profil').'?user='.$b->id)
        ->assertDontSee($b->email);
});

it('tidak pernah membocorkan email user lain di halaman mana pun milik user', function () {
    $a = User::factory()->create();
    $b = User::factory()->create(['email' => 'rahasia-orang-lain@example.test']);

    $this->actingAs($a)
        ->get(route('akun.profil'))
        ->assertDontSee('rahasia-orang-lain@example.test');
});

it('memaksa tamu login sebelum membuka halaman akun', function () {
    $this->get(route('akun.profil'))->assertRedirect('/login');
});

it('menolak user membuka halaman konfirmasi booking milik user lain', function () {
    $pemilik = User::factory()->create();
    $userLain = User::factory()->create();
    $booking = Booking::factory()->for($pemilik)->create();

    $this->actingAs($userLain)
        ->get(route('booking.show', $booking))
        ->assertForbidden();
});

it('menolak tamu membuka booking guest lain tanpa tanda tangan URL yang cocok', function () {
    $bookingA = Booking::factory()->create(['user_id' => null]);
    $bookingB = Booking::factory()->create(['user_id' => null]);

    $tandaTanganUntukA = URL::signedRoute('booking.show', $bookingA);
    $urlDenganIdSalah = str_replace((string) $bookingA->id, (string) $bookingB->id, $tandaTanganUntukA);

    $this->get($urlDenganIdSalah)->assertForbidden();
});
