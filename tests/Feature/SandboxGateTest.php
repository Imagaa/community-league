<?php

use App\Http\Middleware\SandboxGate;

it('tidak melakukan apa pun kalau nonaktif (default)', function () {
    $this->get('/')->assertOk();
});

it('redirect ke halaman gate kalau aktif dan browser belum punya cookie', function () {
    config(['services.sandbox_gate.enabled' => true]);

    $this->get('/')->assertRedirect(route('sandbox-gate.show'));
});

it('meloloskan webhook sumopod walau gate aktif', function () {
    config(['services.sandbox_gate.enabled' => true]);

    // Tidak perlu payload valid -- yang diuji cuma middleware tidak menggerbangnya (respons
    // akhirnya urusan verifikasi webhook sendiri, bukan redirect ke halaman gate).
    $response = $this->post('/webhooks/sumopod', []);

    expect($response->headers->get('Location'))->not->toBe(route('sandbox-gate.show'));
});

it('meloloskan health check /up walau gate aktif', function () {
    config(['services.sandbox_gate.enabled' => true]);

    $this->get('/up')->assertOk();
});

it('halaman gate sendiri bisa diakses tanpa cookie (tidak infinite redirect)', function () {
    config(['services.sandbox_gate.enabled' => true]);

    $this->get(route('sandbox-gate.show'))->assertOk();
});

it('meloloskan request yang sudah punya cookie sandbox_access', function () {
    config(['services.sandbox_gate.enabled' => true]);

    $this->withCookie(SandboxGate::COOKIE, '1')->get('/')->assertOk();
});

it('meloloskan request ke domain admin, termasuk endpoint livewire/update milik Filament', function () {
    // Regresi ditemukan lewat laporan manual: tombol "Reset"/"Buat" di tab Sandbox panel admin
    // redirect ke halaman gate saat diklik. Sebabnya: Livewire mendaftarkan /livewire/update
    // secara GLOBAL tanpa domain() lewat grup 'web' (dipakai SEMUA komponen Livewire, termasuk
    // punya Filament) -- browser admin tidak pernah dapat cookie sandbox_access (beda domain
    // dari gerbang publik) sehingga aksi Livewire panel ikut ke-redirect.
    config(['services.sandbox_gate.enabled' => true]);

    $response = $this->post('http://'.config('app.admin_domain').'/livewire/update', []);

    expect($response->headers->get('Location'))->not->toBe(route('sandbox-gate.show'));
});
