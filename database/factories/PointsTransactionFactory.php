<?php

namespace Database\Factories;

use App\Models\PointsTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PointsTransaction> */
class PointsTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => 100,
            'type' => 'admin_grant',
        ];
    }
}
