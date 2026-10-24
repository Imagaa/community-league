<?php

it('tamu bisa membuka beranda tanpa login', function () {
    $this->get('/')->assertOk();
});

it('menyediakan health check untuk uptime monitor', function () {
    $this->get('/up')->assertOk();
});

it('menyimpan waktu dalam UTC dan menampilkannya dalam WIB', function () {
    expect(config('app.timezone'))->toBe('UTC')
        ->and(config('app.display_timezone'))->toBe('Asia/Jakarta');
});

it('memakai locale Indonesia', function () {
    expect(config('app.locale'))->toBe('id');
});
