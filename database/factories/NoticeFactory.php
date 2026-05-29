<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notice>
 */
class NoticeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(6),
            'content' => $this->faker->paragraphs(3, true),
            'is_pinned' => false,
            'view_count' => 0,
            'created_by' => User::factory(),
        ];
    }

    public function pinned(): self
    {
        return $this->state(fn () => ['is_pinned' => true]);
    }
}
