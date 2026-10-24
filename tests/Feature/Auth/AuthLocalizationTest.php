<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_login_dalam_bahasa_indonesia(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Masuk ke akun Anda');
        $response->assertSee('Lupa password?');
        $response->assertDontSee('Log in to your account');
        $response->assertDontSee('Forgot your password?');
    }

    public function test_halaman_register_dalam_bahasa_indonesia(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertSee('Buat akun baru');
        $response->assertSee('Nama lengkap');
        $response->assertDontSee('Create an account');
        $response->assertDontSee('Full name');
    }

    public function test_halaman_forgot_password_dalam_bahasa_indonesia(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
        $response->assertSee('Lupa password');
        $response->assertSee('Kirim link reset password');
        $response->assertDontSee('Forgot password');
        $response->assertDontSee('Email password reset link');
    }

    public function test_halaman_reset_password_dalam_bahasa_indonesia(): void
    {
        $response = $this->get(route('password.reset', 'faketoken'));

        $response->assertOk();
        $response->assertSee('Masukkan password baru Anda di bawah');
        $response->assertDontSee('Please enter your new password below');
    }

    public function test_halaman_confirm_password_dalam_bahasa_indonesia(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('password.confirm'));

        $response->assertOk();
        $response->assertSee('Konfirmasi password');
        $response->assertDontSee('Confirm password');
    }

    public function test_halaman_verify_email_dalam_bahasa_indonesia(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $response = $this->get(route('verification.notice'));

        $response->assertOk();
        $response->assertSee('Verifikasi email');
        $response->assertSee('Kirim ulang email verifikasi');
        $response->assertDontSee('Email verification');
        $response->assertDontSee('Resend verification email');
    }
}
