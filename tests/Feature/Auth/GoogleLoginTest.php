<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function fakeGoogleUser(string $email, string $id = 'google-123'): SocialiteUser
{
    $user = new SocialiteUser;
    $user->id = $id;
    $user->name = 'Budi Santoso';
    $user->email = $email;
    $user->avatar = 'https://example.test/avatar.jpg';

    return $user;
}

it('membuat akun baru saat login Google pertama kali', function () {
    Socialite::shouldReceive('driver->user')
        ->andReturn(fakeGoogleUser('budi@example.test'));

    $this->get('/auth/google/callback')->assertRedirect('/');

    $user = User::where('email', 'budi@example.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->google_id)->toBe('google-123')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->referral_code)->toHaveLength(8);
});

it('menautkan akun email yang sudah ada ke Google', function () {
    $existing = User::factory()->create([
        'email' => 'budi@example.test',
        'google_id' => null,
    ]);

    Socialite::shouldReceive('driver->user')
        ->andReturn(fakeGoogleUser('budi@example.test'));

    $this->get('/auth/google/callback');

    expect($existing->fresh()->google_id)->toBe('google-123')
        ->and(User::where('email', 'budi@example.test')->count())->toBe(1);
});

it('tidak membuat akun ganda saat login Google kedua kali', function () {
    Socialite::shouldReceive('driver->user')
        ->andReturn(fakeGoogleUser('budi@example.test'));

    $this->get('/auth/google/callback');
    auth()->logout();
    $this->get('/auth/google/callback');

    expect(User::where('email', 'budi@example.test')->count())->toBe(1);
});
