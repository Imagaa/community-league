<?php

namespace Database\Factories;

use App\Models\MembershipBenefit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MembershipBenefit> */
class MembershipBenefitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'icon' => 'star',
            'text' => $this->faker->sentence(),
            'sort_order' => 0,
        ];
    }
}
