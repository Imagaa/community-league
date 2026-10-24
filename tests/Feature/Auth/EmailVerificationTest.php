<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('mengirim email verifikasi setelah registrasi', function () {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Budi Santoso',
        'email' => 'budi@example.test',
        'phone' => '081234567890',
        'password' => 'Rahasia-Panjang123!',
        'password_confirmation' => 'Rahasia-Panjang123!',
        'terms' => '1',
    ]);

    Notification::assertSentTo(
        User::where('email', 'budi@example.test')->first(),
        VerifyEmail::class
    );
});

it('mengarahkan ke halaman cek email setelah registrasi, bukan langsung ke home', function () {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Budi Santoso',
        'email' => 'budi-redirect@example.test',
        'phone' => '081234567890',
        'password' => 'Rahasia-Panjang123!',
        'password_confirmation' => 'Rahasia-Panjang123!',
        'terms' => '1',
    ])->assertRedirect(route('verification.notice', absolute: false));
});

it('menyajikan halaman berhasil diverifikasi dengan tombol ke home', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('verification.success'))
        ->assertOk()
        ->assertSee('Akun Anda berhasil diverifikasi')
        ->assertSee('Mulai cari match untukmu')
        ->assertSee(route('home'), false);
});

it('menyajikan halaman verifikasi email', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk();
});

it('halaman verifikasi email menampilkan email yang dipakai', function () {
    $user = User::factory()->unverified()->create(['email' => 'typo@example.test']);

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertSee('typo@example.test');
});

it('kirim ulang email verifikasi dibatasi rate limit', function () {
    $user = User::factory()->unverified()->create();
    $this->actingAs($user);

    for ($i = 1; $i <= 3; $i++) {
        $this->post(route('verification.send'))->assertStatus(302);
    }

    $this->post(route('verification.send'))->assertStatus(429);
});

it('memverifikasi email lewat tautan bertanda tangan', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('verification.success', absolute: false));
});

it('menolak verifikasi dengan hash yang salah', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')],
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('tidak memicu event lagi kalau email sudah terverifikasi sebelumnya', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect(route('verification.success', absolute: false));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertNotDispatched(Verified::class);
});

it('tidak menggagalkan registrasi kalau pengiriman email verifikasi gagal', function () {
    Notification::shouldReceive('send')->once()->andThrow(new Exception('Resend down'));
    Log::shouldReceive('error')->atLeast()->once();

    $this->post('/register', [
        'name' => 'Budi Santoso',
        'email' => 'budi5@example.test',
        'phone' => '081234567890',
        'password' => 'Rahasia-Panjang123!',
        'password_confirmation' => 'Rahasia-Panjang123!',
        'terms' => '1',
    ])->assertRedirect(route('verification.notice', absolute: false));

    expect(User::where('email', 'budi5@example.test')->exists())->toBeTrue();
});
