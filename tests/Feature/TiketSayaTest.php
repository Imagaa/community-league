<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TiketSayaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('tiket-saya'))->assertRedirect(route('login'));
    }

    public function test_menampilkan_booking_milik_user_urut_terbaru(): void
    {
        $user = User::factory()->create();
        $match = GameMatch::factory()->create(['title' => 'Match Tiket Saya']);

        $lama = Booking::factory()->create(['user_id' => $user->id, 'match_id' => $match->id, 'created_at' => now()->subDay()]);
        $baru = Booking::factory()->create(['user_id' => $user->id, 'match_id' => $match->id, 'created_at' => now()]);

        $response = $this->actingAs($user)->get(route('tiket-saya'));

        $response->assertOk()->assertSeeInOrder([$baru->booking_code, $lama->booking_code]);
    }

    public function test_tidak_menampilkan_booking_milik_user_lain(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $bookingB = Booking::factory()->create(['user_id' => $b->id]);

        $this->actingAs($a)
            ->get(route('tiket-saya'))
            ->assertDontSee($bookingB->booking_code);
    }
}
