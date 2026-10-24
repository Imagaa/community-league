<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterPasswordChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_register_menampilkan_checklist_syarat_password(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertSee('Minimal 8 karakter');
        $response->assertSee('Huruf besar dan huruf kecil');
        $response->assertSee('Mengandung angka');
        $response->assertSee('Mengandung simbol');
    }

    public function test_halaman_register_menampilkan_validator_realtime_konfirmasi_password(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertSee('Password cocok');
        $response->assertSee('Password tidak cocok');
    }
}
