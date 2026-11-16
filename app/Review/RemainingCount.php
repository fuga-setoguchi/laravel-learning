<?php

declare(strict_types=1);

namespace App\Review;

/** 今日の復習の残り件数（docs/05_review-logic.md 4.3） */
final readonly class RemainingCount
{
    public function __construct(
        /** 期限が来た問題 + 再出題 */
        public int $total,
        /** うち再出題 */
        public int $practice,
    ) {}
}
