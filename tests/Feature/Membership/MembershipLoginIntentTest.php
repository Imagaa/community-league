<?php

it('login-intent menyimpan niat lalu redirect ke register', function () {
    $this->post(route('membership.login-intent'))
        ->assertRedirect(route('register'));

    expect(session('membership_purchase_intent'))->toBeTrue();
});

it('daftar dengan niat beli membership kembali ke halaman membership, bukan cek-email', function () {
    $this->withSession(['membership_purchase_intent' => true])
        ->post('/register', [
            'name' => 'Calon Member',
            'email' => 'calon@example.test',
            'phone' => '081234567890',
            'password' => 'Rahasia-Panjang123!',
            'password_confirmation' => 'Rahasia-Panjang123!',
            'terms' => '1',
        ])
        ->assertRedirect(route('membership'));
});
