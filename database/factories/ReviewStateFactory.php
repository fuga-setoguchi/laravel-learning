<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Question;
use App\Models\ReviewState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 既定はレッスン完了直後の状態（段階1・次回は明日）。
 *
 * @extends Factory<ReviewState>
 */
class ReviewStateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'stage' => 1,
            'next_review_on' => today()->addDay(),
        ];
    }

    /** 期限が来ている（既定は今日が期限） */
    public function due(?CarbonImmutable $on = null): static
    {
        return $this->state(fn (): array => ['next_review_on' => $on ?? today()]);
    }

    /** 期限が来ていない（明日が期限） */
    public function notDue(): static
    {
        return $this->state(fn (): array => ['next_review_on' => today()->addDay()]);
    }

    public function stage(int $stage): static
    {
        return $this->state(fn (): array => ['stage' => $stage]);
    }
}
