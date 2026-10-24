<?php

use Illuminate\Http\Request;

it('route() selalu menghasilkan URL berdomain utama walau request datang dari domain admin', function () {
    // Regresi: notifikasi yang dikirim dari dalam aksi panel admin (host admin.communityleague.id)
    // generate link salah domain -- Chrome/Android menganggapnya keluar dari origin PWA dan
    // membuka browser eksternal alih-alih navigasi in-app. AppServiceProvider::configureDefaults()
    // sekarang paksa root URL ke config('app.url') supaya route() tanpa ->domain() eksplisit
    // selalu pakai domain publik, apa pun host request yang sedang aktif.
    $adminRequest = Request::create('https://'.config('app.admin_domain').'/some-admin-path');
    app()->instance('request', $adminRequest);
    app('url')->setRequest($adminRequest);

    expect(route('marketplace'))->toStartWith(config('app.url'))
        ->and(route('marketplace'))->not->toContain(config('app.admin_domain'));
});
