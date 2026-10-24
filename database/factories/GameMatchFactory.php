<?php

namespace Database\Factories;

use App\Models\GameMatch;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<GameMatch> */
class GameMatchFactory extends Factory
{
    protected $model = GameMatch::class;

    public function definition(): array
    {
        $title = 'Mini Soccer '.fake()->unique()->numberBetween(1, 100000);

        return [
            'venue_id' => Venue::factory(),
            'field_name' => 'Lapangan A',
            'type' => 'minisoccer',
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(6),
            'description' => fake()->sentence(),
            'poster_path' => null,
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(7)->addHours(2),
            'capacity' => 10,
            'price_regular' => 35000,
            'price_member' => 30000,
            'points_reward' => 10,
            'member_opens_at' => now()->subDay(),
            'public_opens_at' => now()->subHour(),
            'status' => 'published',
            'last_completed_wizard_step' => 4,
            'score_a' => null,
            'score_b' => null,
        ];
    }
}
