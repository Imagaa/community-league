<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleAuthButtonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.client_id' => 'fake-client-id']);
    }

    public function test_tombol_google_tampil_di_halaman_login_dengan_label_masuk(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Masuk dengan Google');
        $response->assertSee(route('auth.google.redirect'), false);
    }

    public function test_halaman_login_tidak_lagi_punya_opsi_masuk_dengan_passkey(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertDontSee('Masuk dengan passkey');
        $response->assertSee('atau');
    }

    public function test_halaman_konfirmasi_password_tidak_lagi_punya_opsi_passkey(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('password.confirm'));

        $response->assertOk();
        $response->assertDontSee('Konfirmasi dengan passkey');
    }

    public function test_tombol_google_tampil_di_halaman_register_dengan_label_daftar(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertSee('Daftar dengan Google');
        $response->assertSee(route('auth.google.redirect'), false);
    }
}
