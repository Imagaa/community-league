<?php

use App\Models\MembershipSettings;

it('tamu bisa membuka halaman membership', function () {
    MembershipSettings::current()->update(['enabled' => true]);
    // Tamu (belum login) lihat "Login untuk Aktifkan", bukan "Aktifkan Membership" -- membeli
    // membership wajib login+verifikasi dulu (lihat MembershipPageTest).
    $this->get(route('membership'))
        ->assertOk()
        ->assertSee('Community League Membership')
        ->assertSee('Login untuk Aktifkan');
});

it('nav bawah menampilkan 5 tab termasuk Feed', function () {
    $response = $this->get(route('membership'));

    $response->assertSee('Home')
        ->assertSee('Match')
        ->assertSee('Membership')
        ->assertSee('Feed')
        ->assertSee('Akun');
});

it('tamu bisa membuka halaman feed dan melihat coming soon', function () {
    $this->get(route('feed'))
        ->assertOk()
        ->assertSee('Feed Community League')
        ->assertSee('Sedang kami siapkan');
});
