<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(fn () => Role::findOrCreate('super_admin'));

it('menghapus akun publik tapi tidak menyentuh akun admin', function () {
    $publicUser = User::factory()->create();
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->artisan('app:wipe-test-accounts', ['--force' => true])
        ->assertSuccessful();

    expect(User::find($publicUser->id))->toBeNull()
        ->and(User::find($admin->id))->not->toBeNull();
});

it('tidak melakukan apa-apa kalau tidak ada akun publik', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->artisan('app:wipe-test-accounts', ['--force' => true])
        ->assertSuccessful();

    expect(User::find($admin->id))->not->toBeNull();
});
