<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(fn () => RateLimiter::clear('login'));

it('memblokir percobaan login ke-6 dalam satu menit', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $i) {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password-salah',
        ]);
    }

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password-salah',
    ])->assertStatus(429);
});

it('membatasi permintaan reset password 3 kali per jam', function () {
    $user = User::factory()->create();

    foreach (range(1, 3) as $i) {
        $this->post('/forgot-password', ['email' => $user->email]);
    }

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertStatus(429);
});

it('mengirim header keamanan pada setiap respons', function () {
    $response = $this->get('/login');

    expect($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
});
