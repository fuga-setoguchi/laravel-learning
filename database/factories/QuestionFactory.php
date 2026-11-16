<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\QuestionChoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 既定は想起カード。選択式・AIコードレビュー問題は choice() / codeReview() で作り、選択肢 a〜c（正解は a）も作る。
 *
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    private static int $sequence = 0;

    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'type' => QuestionType::Recall,
            'position' => ++self::$sequence,
            'prompt' => fake()->realText(40),
            'answer' => fake()->realText(60),
        ];
    }

    /**
     * id を指定しなければ、教材の規則どおり「レッスンid-c番号 / -q番号」で決める
     */
    public function configure(): static
    {
        return $this
            ->afterMaking(function (Question $question): void {
                if (! array_key_exists('id', $question->getAttributes())) {
                    $suffix = $question->type === QuestionType::Recall ? 'c' : 'q';
                    $question->id = "{$question->lesson_id}-{$suffix}{$question->position}";
                }
            })
            ->afterCreating(function (Question $question): void {
                if ($question->type === QuestionType::Recall || $question->choices()->exists()) {
                    return;
                }

                foreach (['a' => true, 'b' => false, 'c' => false] as $key => $isCorrect) {
                    QuestionChoice::factory()->for($question)->create(['key' => $key, 'is_correct' => $isCorrect]);
                }
            });
    }

    public function choice(): static
    {
        return $this->state(fn (): array => [
            'type' => QuestionType::Choice,
            'answer' => null,
            'explanation' => fake()->realText(60),
        ]);
    }

    public function codeReview(): static
    {
        return $this->state(fn (): array => [
            'type' => QuestionType::CodeReview,
            'code' => "public function store(Request \$request)\n{\n    \$service = new PaymentService();\n}",
            'code_language' => 'php',
            'answer' => null,
            'explanation' => fake()->realText(60),
        ]);
    }

    public function retired(): static
    {
        return $this->state(fn (): array => ['retired_at' => now()]);
    }
}
