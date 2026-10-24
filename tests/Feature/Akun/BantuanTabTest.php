<?php

use App\Models\User;

it('tab bantuan menampilkan kontak email dan tautan legal', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('akun.bantuan'))
        ->assertOk()
        ->assertSee('halo@communityleague.id')
        ->assertSee(route('legal.terms'), false)
        ->assertSee(route('legal.privacy'), false);
});

it('tamu (belum login) tetap bisa buka tab bantuan, tidak diarahkan ke login', function () {
    $this->get(route('akun.bantuan'))
        ->assertOk()
        ->assertSee('halo@communityleague.id');
});
