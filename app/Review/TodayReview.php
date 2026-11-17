<?php

declare(strict_types=1);

namespace App\Review;

use Carbon\CarbonImmutable;

/** 今日の復習のまとめ（docs/05_review-logic.md 4.4） */
final readonly class TodayReview
{
    /**
     * @param  array{pass: int, hold: int, fail: int}  $grades  今日の期限の出題の回答を、評価ごとに数えたもの
     */
    public function __construct(
        public TodayReviewStatus $status,
        public RemainingCount $remaining,
        public array $grades,
        /** 今日より後で最も早い次回の復習日（なければ null） */
        public ?CarbonImmutable $nextReviewOn,
    ) {}

    public function answeredCount(): int
    {
        return array_sum($this->grades);
    }
}
