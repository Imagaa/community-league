<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Venue> */
class VenueFactory extends Factory
{
    protected $model = Venue::class;

    public function definition(): array
    {
        return [
            'city_id' => City::factory(),
            'name' => fake()->company().' Arena',
            'address' => fake()->address(),
            'maps_url' => null,
            'is_active' => true,
        ];
    }
}
