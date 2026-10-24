<?php

use App\Models\User;
use Livewire\Livewire;

it('user bisa membuka halaman profil dan lihat data saat ini', function () {
    $user = User::factory()->create(['name' => 'Budi Santoso', 'nickname' => 'Budi']);

    $this->actingAs($user)
        ->get(route('akun.profil'))
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertSee('Budi');
});

it('halaman profil punya tombol kembali ke menu akun', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('akun.profil'))
        ->assertSee(route('akun'), false);
});

it('user bisa update data diri, ukuran, dan alamat', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::akun.profil')
        ->set('name', 'Nama Baru')
        ->set('nickname', 'Panggilan')
        ->set('phone', '081234567890')
        ->set('birthdate', '1998-01-01')
        ->set('shirt_size', 'L')
        ->set('shoe_size', '42')
        ->set('address', 'Jl. Mawar No. 5')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('Nama Baru')
        ->and($user->nickname)->toBe('Panggilan')
        ->and($user->shirt_size)->toBe('L')
        ->and($user->shoe_size)->toBe(42)
        ->and($user->address)->toBe('Jl. Mawar No. 5');
});

it('nama panggilan, telfon, tanggal lahir, ukuran, dan alamat opsional -- boleh dikosongkan', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::akun.profil')
        ->set('name', 'Nama Wajib')
        ->set('nickname', null)
        ->set('phone', null)
        ->set('birthdate', null)
        ->set('shirt_size', null)
        ->set('shoe_size', null)
        ->set('address', null)
        ->call('save')
        ->assertHasNoErrors();
});
