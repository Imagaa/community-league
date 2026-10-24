<?php

use App\Models\User;

it('header desktop pakai warna merah sama seperti header mobile, bukan putih', function () {
    // Regresi diminta user: header desktop putih, header mobile merah -- tidak konsisten.
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('bg-red-600 px-6 py-4', false);
});

it('tombol Masuk dan Daftar ada di satu wrapper flex yang sama, tidak lepas sendiri-sendiri', function () {
    // Regresi diminta user: tombol Masuk/Daftar bertumpuk -- keduanya sekarang dibungkus satu
    // <div class="flex ... gap-3"> supaya selalu sebaris, tidak pernah kepisah jadi dua baris.
    $html = $this->get(route('home'))->assertOk()->getContent();

    preg_match('/ms-auto flex shrink-0 items-center gap-3">(.*?)<\/div>\s*<\/header>/s', (string) $html, $matches);

    expect($matches)->toHaveCount(2)
        ->and($matches[1])->toContain('Masuk')
        ->and($matches[1])->toContain('Daftar');
});

it('tab yang sedang aktif di header desktop ditandai jelas', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('match'))
        ->assertOk()
        ->assertSee('aria-current="page"', false);
});
