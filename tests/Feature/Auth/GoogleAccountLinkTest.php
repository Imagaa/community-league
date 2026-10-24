<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

it('user yang sudah login bisa menghubungkan akun Google-nya', function () {
    $user = User::factory()->create(['google_id' => null]);

    $googleUser = new SocialiteUser;
    $googleUser->id = 'google-123';
    $googleUser->email = 'lain@example.com';
    $googleUser->name = 'Nama Google';

    Socialite::shouldReceive('driver->user')->andReturn($googleUser);

    $this->actingAs($user)
        ->get(route('auth.google.callback'))
        ->assertRedirect(route('akun.keamanan'));

    expect($user->refresh()->google_id)->toBe('google-123');
});

it('tidak bisa menghubungkan Google yang sudah dipakai akun lain', function () {
    $existingOwner = User::factory()->create(['google_id' => 'google-999']);
    $user = User::factory()->create(['google_id' => null]);

    $googleUser = new SocialiteUser;
    $googleUser->id = 'google-999';
    $googleUser->email = 'lain@example.com';

    Socialite::shouldReceive('driver->user')->andReturn($googleUser);

    $this->actingAs($user)
        ->get(route('auth.google.callback'))
        ->assertRedirect(route('akun.keamanan'))
        ->assertSessionHasErrors('google');

    expect($user->refresh()->google_id)->toBeNull();
    expect($existingOwner->refresh()->google_id)->toBe('google-999');
});
