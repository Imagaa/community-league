<?php

use App\Models\Membership;
use App\Models\MembershipBenefit;
use App\Models\RewardItem;
use App\Models\User;

it('guest melihat paywall dengan tombol Login untuk Aktifkan', function () {
    $this->get(route('membership'))
        ->assertOk()
        ->assertSee('Login untuk Aktifkan')
        ->assertDontSee('Aktifkan Membership', false);
});

it('user login belum verifikasi melihat alert verifikasi, bukan tombol beli', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('membership'))
        ->assertOk()
        ->assertSee('Verifikasi sekarang')
        ->assertDontSee('Aktifkan Membership');
});

it('user login terverifikasi tanpa membership melihat tombol Aktifkan Membership', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get(route('membership'))
        ->assertOk()
        ->assertSee('Aktifkan Membership');
});

it('user dengan membership aktif melihat kartu dan tanggal berakhir', function () {
    $user = User::factory()->create();
    Membership::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addDays(200)]);
    MembershipBenefit::factory()->create(['text' => 'Keuntungan Uji']);

    $this->actingAs($user)->get(route('membership'))
        ->assertOk()
        ->assertSee('Keuntungan Uji')
        ->assertSee('Poin terkumpul')
        ->assertDontSee('Aktifkan Membership');
});

it('kartu membership aktif punya manfaat collapsible dan marketplace tertanam di halaman yang sama', function () {
    // Regresi diminta user: "Manfaat" sekarang collapsible (bukan link scroll) diposisikan di
    // bawah kartu digital, poin pindah ke dalam kartu digital sebelah kanan sebagai kotak glass
    // yang bisa ditekan ke Riwayat Poin. Kotak "Tukar poin di marketplace"/"Riwayat penukaran"
    // yang lama sudah dihapus -- marketplace tetap tertanam langsung di halaman yang sama.
    $user = User::factory()->create();
    Membership::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addDays(200)]);
    $item = RewardItem::factory()->create(['name' => 'Kaos Uji Tertanam', 'active' => true, 'stock' => 5]);

    $this->actingAs($user)->get(route('membership'))
        ->assertOk()
        ->assertSee('<details', false)
        ->assertSee('Manfaat yang Anda terima sebagai membership')
        ->assertSee('backdrop-blur-md', false)
        ->assertSee('href="'.route('membership.redemptions').'"', false)
        ->assertDontSee('Tukar poin di marketplace')
        ->assertDontSee('Riwayat penukaran')
        ->assertSee('Kaos Uji Tertanam');
});

it('user dengan membership lapsed melihat halaman perpanjang yang berbeda', function () {
    $user = User::factory()->create();
    Membership::factory()->create(['user_id' => $user->id, 'expires_at' => now()->subDays(5)]);

    $this->actingAs($user)->get(route('membership'))
        ->assertOk()
        ->assertSee('Perpanjang durasi manfaatmu', false);
});
