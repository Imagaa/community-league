<?php

namespace Tests\Feature\Matches;

use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchShowBookingWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_melihat_tombol_login_untuk_beli(): void
    {
        $match = GameMatch::factory()->create(['status' => 'open']);

        $this->get(route('match.show', $match))
            ->assertOk()
            ->assertSee('Masuk untuk beli');
    }

    public function test_user_login_melihat_tombol_beli_langsung(): void
    {
        $user = User::factory()->create();
        $match = GameMatch::factory()->create(['status' => 'open']);

        $this->actingAs($user)
            ->get(route('match.show', $match))
            ->assertOk()
            ->assertSee('wire:click="buyAuthenticated"', false);
    }
}
