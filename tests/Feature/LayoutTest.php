<?php

use App\Models\User;

it('menampilkan lima tab navigasi bawah', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('akun.profil'))
        ->assertSee('Home')
        ->assertSee('Match')
        ->assertSee('Membership')
        ->assertSee('Feed')
        ->assertSee('Akun');
});

it('menyediakan manifest PWA', function () {
    $this->get('/manifest.json')
        ->assertOk()
        ->assertJsonPath('name', 'Community League');
});
