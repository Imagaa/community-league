<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Booking> */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'match_id' => GameMatch::factory(),
            'user_id' => User::factory(),
            'guest_name' => null,
            'guest_email' => null,
            'guest_phone' => null,
            'quantity' => 1,
            'unit_price' => 35000,
            'total' => 35000,
            'status' => 'pending_payment',
            'hold_expires_at' => now()->addMinutes(30),
            'terms_version' => User::TERMS_VERSION,
        ];
    }
}
