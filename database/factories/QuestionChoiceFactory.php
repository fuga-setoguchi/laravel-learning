<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionChoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionChoice>
 */
class QuestionChoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'question_id' => Question::factory()->choice(),
            'key' => 'd',
            'body' => fake()->sentence(),
            'is_correct' => false,
        ];
    }
}
