<?php

namespace Database\Factories;

use App\Models\Blog;
use App\Models\BlogBuffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogBuffer>
 */
class BlogBufferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //'blog_id' => Blog::query()->inRandomOrder()->value('id'),
            'buffer_url' => fake()->domainName(),
        ];
    }
}
