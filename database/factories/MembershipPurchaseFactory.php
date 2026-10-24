<?php

namespace Database\Factories;

use App\Models\MembershipPurchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MembershipPurchase> */
class MembershipPurchaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => 100000,
            'status' => 'pending_payment',
        ];
    }
}
