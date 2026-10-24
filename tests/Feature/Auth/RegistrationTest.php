<?php

use App\Models\User;

it('menyajikan halaman registrasi', function () {
    $this->get(route('register'))->assertOk();
});

it('checkbox syarat dan ketentuan menampilkan teks dan tautan, tidak kosong', function () {
    // Regresi: <flux:checkbox>...</flux:checkbox> di versi Flux ini membuang isi slot default
    // sepenuhnya (cuma prop label/description string polos yang dirender) -- checkbox ini pakai
    // <input> native + <label> manual supaya teks dan tautannya benar-benar tampil.
    $response = $this->get(route('register'));

    $response->assertOk();
    $response->assertSee('Saya menyetujui');
    $response->assertSee(route('legal.terms'), false);
    $response->assertSee(route('legal.privacy'), false);
});

it('menyimpan nomor HP saat registrasi', function () {
    $this->post('/register', [
        'name' => 'Budi Santoso',
        'email' => 'budi@example.test',
        'phone' => '081234567890',
        'password' => 'Rahasia-Panjang123!',
        'password_confirmation' => 'Rahasia-Panjang123!',
        'terms' => '1',
    ]);

    $user = User::where('email', 'budi@example.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->phone)->toBe('081234567890');
});

it('menolak registrasi tanpa nomor HP', function () {
    $this->post('/register', [
        'name' => 'Budi Santoso',
        'email' => 'budi2@example.test',
        'password' => 'Rahasia-Panjang123!',
        'password_confirmation' => 'Rahasia-Panjang123!',
        'terms' => '1',
    ])->assertSessionHasErrors('phone');

    expect(User::where('email', 'budi2@example.test')->exists())->toBeFalse();
});

it('menolak registrasi tanpa persetujuan syarat dan ketentuan', function () {
    $this->post('/register', [
        'name' => 'Budi Santoso',
        'email' => 'budi3@example.test',
        'phone' => '081234567890',
        'password' => 'Rahasia-Panjang123!',
        'password_confirmation' => 'Rahasia-Panjang123!',
    ])->assertSessionHasErrors('terms');
});

it('mencatat versi syarat dan ketentuan yang disetujui', function () {
    $this->post('/register', [
        'name' => 'Budi Santoso',
        'email' => 'budi4@example.test',
        'phone' => '081234567890',
        'password' => 'Rahasia-Panjang123!',
        'password_confirmation' => 'Rahasia-Panjang123!',
        'terms' => '1',
    ]);

    $user = User::where('email', 'budi4@example.test')->first();

    expect($user->terms_version)->toBe(User::TERMS_VERSION)
        ->and($user->terms_accepted_at)->not->toBeNull();
});

it('memberi setiap user kode referral yang unik', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    expect($a->referral_code)->toHaveLength(8)
        ->and($a->referral_code)->not->toBe($b->referral_code);
});
