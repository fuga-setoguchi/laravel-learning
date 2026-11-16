<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReviewMode;
use App\Enums\ReviewResult;
use App\Models\Question;
use App\Models\ReviewLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 既定は、今の時刻に期限の出題（scheduled）で「思い出せた」と回答した記録。
 *
 * @extends Factory<ReviewLog>
 */
class ReviewLogFactory extends Factory
{
    public function definition(): array
    {
        $now = now();

        return [
            'question_id' => Question::factory(),
            'mode' => ReviewMode::Scheduled,
            'result' => ReviewResult::Remembered,
            'stage_before' => 1,
            'stage_after' => 2,
            'answered_at' => $now,
            'answered_on' => $now->startOfDay(),
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => ['mode' => ReviewMode::Scheduled]);
    }

    /** 再出題（段階は記録しない） */
    public function practice(): static
    {
        return $this->state(fn (): array => [
            'mode' => ReviewMode::Practice,
            'stage_before' => null,
            'stage_after' => null,
        ]);
    }

    public function result(ReviewResult $result): static
    {
        return $this->state(fn (): array => ['result' => $result]);
    }

    /** 回答日時を指定する（回答日も合わせる） */
    public function answeredAt(CarbonImmutable $at): static
    {
        return $this->state(fn (): array => [
            'answered_at' => $at,
            'answered_on' => $at->startOfDay(),
        ]);
    }

    /** 指定した日時に期限の出題で不正解だった記録（再出題の対象） */
    public function failedAt(CarbonImmutable $at): static
    {
        return $this->scheduled()->result(ReviewResult::Incorrect)->answeredAt($at)
            ->state(fn (): array => ['stage_after' => 1]);
    }
}
