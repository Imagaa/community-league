<?php

namespace Tests\Feature\Auth;

use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseIntentRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_dengan_niat_beli_kembali_ke_halaman_match(): void
    {
        $match = GameMatch::factory()->create(['capacity' => 10]);

        $this->withSession(['purchase_intent' => ['match_id' => $match->id]])
            ->post('/register', [
                'name' => 'Pembeli Baru',
                'email' => 'pembeli@example.test',
                'phone' => '081234567890',
                'password' => 'Rahasia-Panjang123!',
                'password_confirmation' => 'Rahasia-Panjang123!',
                'terms' => '1',
            ])
            ->assertRedirect(route('match.show', $match).'#beli');

        // Booking belum dibuat -- pembeli melengkapi form beli (nama & WA) dulu di halaman match.
        expect(User::where('email', 'pembeli@example.test')->first()->bookings()->count())->toBe(0);
    }

    public function test_masuk_dengan_niat_beli_kembali_ke_halaman_match(): void
    {
        $match = GameMatch::factory()->create();
        $user = User::factory()->create(['password' => 'Rahasia-Panjang123!']);

        $this->withSession(['url.intended' => route('match.show', $match).'#beli'])
            ->post('/login', ['email' => $user->email, 'password' => 'Rahasia-Panjang123!'])
            ->assertRedirect(route('match.show', $match).'#beli');
    }

    public function test_daftar_tanpa_niat_beli_tetap_ke_halaman_verifikasi_email(): void
    {
        $this->post('/register', [
            'name' => 'Daftar Biasa',
            'email' => 'biasa@example.test',
            'phone' => '081234567890',
            'password' => 'Rahasia-Panjang123!',
            'password_confirmation' => 'Rahasia-Panjang123!',
            'terms' => '1',
        ])->assertRedirect(route('verification.notice', absolute: false));
    }

    public function test_match_penuh_saat_daftar_tidak_menggagalkan_registrasi(): void
    {
        $match = GameMatch::factory()->create(['status' => 'open', 'capacity' => 1]);
        Booking::factory()->create(['match_id' => $match->id, 'status' => 'paid', 'quantity' => 1]);

        $this->withSession(['purchase_intent' => ['match_id' => $match->id, 'quantity' => 1]])
            ->post('/register', [
                'name' => 'Kehabisan Slot',
                'email' => 'kehabisan@example.test',
                'phone' => '081234567890',
                'password' => 'Rahasia-Panjang123!',
                'password_confirmation' => 'Rahasia-Panjang123!',
                'terms' => '1',
            ]);

        expect(User::where('email', 'kehabisan@example.test')->exists())->toBeTrue();
    }
}
