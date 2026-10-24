<?php

namespace Database\Factories;

use App\Models\RewardItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RewardItem> */
class RewardItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'point_cost' => 100,
            'stock' => 10,
            'active' => true,
            'sort_order' => 0,
        ];
    }
}
