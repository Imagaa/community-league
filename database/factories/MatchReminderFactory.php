<?php

namespace Database\Factories;

use App\Models\GameMatch;
use App\Models\MatchReminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MatchReminder> */
class MatchReminderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'match_id' => GameMatch::factory(),
            'user_id' => User::factory(),
        ];
    }
}
