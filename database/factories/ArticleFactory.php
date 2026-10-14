<?php

namespace Database\Factories;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->sentence(5, false));

        return [
            'author_id' => User::factory()->admin(),
            'title' => $title,
            'slug' => Str::slug($title),
            'image' => null,
            'body' => '<p>'.implode('</p><p>', fake()->paragraphs(3)).'</p>',
            'status' => ArticleStatus::Published,
            'published_at' => now()->subDay(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ArticleStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ArticleStatus::Published,
            'published_at' => now()->addWeek(),
        ]);
    }
}
