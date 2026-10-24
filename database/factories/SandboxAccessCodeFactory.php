<?php

namespace Database\Factories;

use App\Models\SandboxAccessCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SandboxAccessCode> */
class SandboxAccessCodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'pin' => (string) $this->faker->numberBetween(100000, 999999),
            'used_at' => null,
        ];
    }
}
