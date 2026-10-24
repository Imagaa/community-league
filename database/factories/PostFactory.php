<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Post> */
class PostFactory extends Factory
{
    public function definition(): array
    {
        $title = $this->faker->sentence(4);

        return [
            'type' => 'news',
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(6),
            'excerpt' => $this->faker->sentence(12),
            'body' => '<p>'.implode('</p><p>', [
                $this->faker->paragraph(),
                $this->faker->paragraph(),
                $this->faker->paragraph(),
            ]).'</p>',
            'published_at' => now(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function promo(): static
    {
        return $this->state(fn () => [
            'type' => 'promo',
            'body' => null,
            'link_url' => 'https://example.com/promo',
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
