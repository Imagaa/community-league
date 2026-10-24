<?php

use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\Membership;
use App\Models\User;

it('tamu membuka /akun melihat ajakan masuk', function () {
    $this->get(route('akun'))
        ->assertOk()
        ->assertSee('Masuk untuk kelola akun Anda.');
});

it('tamu membuka /akun tetap melihat opsi Tampilan dan tautan Bantuan tanpa login', function () {
    $this->get(route('akun'))
        ->assertOk()
        ->assertSee('Tampilan')
        ->assertSee('value="light"', false)
        ->assertSee('value="dark"', false)
        ->assertSee('value="system"', false)
        ->assertSee('Bantuan')
        ->assertSee(route('akun.bantuan'), false);
});

it('user login membuka /akun melihat menu 6 sub-tab, bukan redirect ke profil', function () {
    $user = User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com']);

    $this->actingAs($user)
        ->get(route('akun'))
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertSee('budi@example.com')
        ->assertSee('Keluar')
        ->assertSee(route('logout'), false)
        ->assertSee('Profil')
        ->assertSee('Keamanan')
        ->assertSee('Notifikasi')
        ->assertSee('Riwayat')
        ->assertSee('Tiket Saya')
        ->assertSee('Bantuan')
        ->assertSee(route('akun.profil'), false)
        ->assertSee(route('akun.keamanan'), false)
        ->assertSee(route('legal.terms'), false)
        ->assertSee(route('legal.privacy'), false);
});

it('user login membuka /akun melihat pemilih Tampilan di bawah Bantuan, di atas tautan legal', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('akun'))
        ->assertOk()
        ->assertSee('Tampilan')
        ->assertSee('value="light"', false)
        ->assertSee('value="dark"', false)
        ->assertSee('value="system"', false)
        ->assertSeeInOrder(['Bantuan', 'Tampilan', 'Syarat & Ketentuan']);
});

it('member aktif melihat badge membership di menu akun, user reguler tidak', function () {
    $member = User::factory()->create();
    Membership::create(['user_id' => $member->id, 'expires_at' => now()->addDays(30)]);
    $reguler = User::factory()->create();

    $this->actingAs($member)
        ->get(route('akun'))
        ->assertSee('verified-badge.webp', false);

    $this->actingAs($reguler)
        ->get(route('akun'))
        ->assertDontSee('verified-badge.webp', false);
});

it('user pending-deletion melihat banner di menu akun', function () {
    $user = User::factory()->create(['scheduled_deletion_at' => now()->addDays(20)]);

    $this->actingAs($user)
        ->get(route('akun'))
        ->assertSee('Batalkan Penghapusan');
});

it('menu akun menampilkan placeholder Wallet', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('akun'))
        ->assertSee('Wallet')
        ->assertSee('Segera Hadir');
});

it('badge tiket aktif muncul di menu akun kalau ada booking lunas yang matchnya belum selesai', function () {
    $user = User::factory()->create();
    $match = GameMatch::factory()->create(['ends_at' => now()->addDay()]);
    Booking::factory()->for($match, 'match')->for($user)->create(['status' => 'paid']);

    $this->actingAs($user)
        ->get(route('akun'))
        ->assertSee('data-test="tiket-aktif-badge"', false);
});

it('badge tiket aktif tidak muncul kalau tidak ada booking lunas yang masih aktif', function () {
    $user = User::factory()->create();
    $matchSelesai = GameMatch::factory()->create(['ends_at' => now()->subHour()]);
    Booking::factory()->for($matchSelesai, 'match')->for($user)->create(['status' => 'paid']);

    $this->actingAs($user)
        ->get(route('akun'))
        ->assertDontSee('data-test="tiket-aktif-badge"', false);
});
