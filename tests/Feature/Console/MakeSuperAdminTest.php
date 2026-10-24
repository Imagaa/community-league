<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(fn () => Role::findOrCreate('super_admin'));

it('menjadikan user super_admin', function () {
    $user = User::factory()->create(['email' => 'owner@example.test']);

    $this->artisan('app:make-super-admin', ['email' => 'owner@example.test'])
        ->assertSuccessful();

    expect($user->fresh()->hasRole('super_admin'))->toBeTrue();
});

it('gagal untuk email yang belum terdaftar', function () {
    $this->artisan('app:make-super-admin', ['email' => 'tidak-ada@example.test'])
        ->assertFailed();
});
