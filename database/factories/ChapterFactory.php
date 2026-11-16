<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Chapter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chapter>
 */
class ChapterFactory extends Factory
{
    /** 章番号は自動採番しないため、ファクトリで連番を振る */
    private static int $sequence = 0;

    public function definition(): array
    {
        return [
            'id' => ++self::$sequence,
            'title' => fake()->realText(20),
        ];
    }

    public function retired(): static
    {
        return $this->state(fn (): array => ['retired_at' => now()]);
    }
}
