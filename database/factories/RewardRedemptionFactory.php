<?php

namespace Database\Factories;

use App\Models\RewardItem;
use App\Models\RewardRedemption;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RewardRedemption> */
class RewardRedemptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reward_item_id' => RewardItem::factory(),
            'points_spent' => 100,
            'status' => 'pending',
            'redeemed_at' => now(),
        ];
    }
}
