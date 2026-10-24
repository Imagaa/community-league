<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_login_tidak_lagi_pakai_dark_mode_paksa(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertDontSee('<html lang="id" class="dark"', false);
    }

    public function test_halaman_login_menampilkan_mobile_header(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Football & Mini Soccer');
    }

    public function test_halaman_login_tidak_lagi_paksa_localstorage_ke_light(): void
    {
        // Dulu ada <script> tambahan yang paksa localStorage['flux.appearance']='light' di sini
        // karena tema gelap belum fungsional di mana pun. Sekarang tema gelap sudah jalan (docs/
        // brand-guideline.md), jadi paksaan itu dihapus -- halaman login ikut preferensi sistem
        // sama seperti halaman lain. Satu-satunya kemunculan baris ini sekarang HARUS berasal
        // dari @fluxAppearance bawaan Flux (fungsi applyAppearance() generik yang menangani
        // ketiga kasus light/dark/system), bukan dari override khusus halaman login -- makanya
        // dihitung tepat 1 kali, bukan cuma "tidak ada" (string itu memang selalu ada di semua
        // halaman lewat Flux).
        $response = $this->get(route('login'));

        $response->assertOk();
        $count = substr_count($response->getContent(), "localStorage.setItem('flux.appearance', 'light')");
        $this->assertSame(1, $count);
    }

    public function test_halaman_login_dan_register_punya_tombol_kembali(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('history.back()', false);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('history.back()', false);
    }

    public function test_tombol_kembali_tidak_lagi_fixed_menimpa_header(): void
    {
        // Regresi: sebelumnya tombol kembali pakai `fixed left-4 top-4`, menimpa
        // mobile-header & logonya. Sekarang harus ada di dalam card (posisi normal).
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertDontSee('fixed left-4 top-4', false);
    }
}
