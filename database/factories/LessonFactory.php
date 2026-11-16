<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Chapter;
use App\Models\Lesson;
use App\ValueObjects\Choice;
use App\ValueObjects\PreQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    private static int $sequence = 0;

    public function definition(): array
    {
        return [
            'chapter_id' => Chapter::factory(),
            'position' => ++self::$sequence,
            'title' => fake()->realText(30),
            'body' => "## 概念\n\n".fake()->realText(200),
            'laravel_version' => '13.x',
            'pre_question' => new PreQuestion(
                prompt: fake()->realText(40),
                choices: [
                    new Choice('a', fake()->realText(20)),
                    new Choice('b', fake()->realText(20)),
                    new Choice('c', fake()->realText(20)),
                ],
                answer: 'b',
                explanation: fake()->realText(60),
            ),
        ];
    }

    /**
     * id を指定しなければ、教材の規則（docs/04_content-format.md）どおり章と順番から決める
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Lesson $lesson): void {
            if (! array_key_exists('id', $lesson->getAttributes())) {
                $lesson->id = sprintf('ch%02d-l%02d', $lesson->chapter_id, $lesson->position);
            }
        });
    }

    public function retired(): static
    {
        return $this->state(fn (): array => ['retired_at' => now()]);
    }
}
