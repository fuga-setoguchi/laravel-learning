<?php

declare(strict_types=1);

namespace App\Review;

use Carbon\CarbonImmutable;

/** ReviewScheduler の計算結果: 新しい段階と次回の復習日 */
final readonly class ScheduledState
{
    public function __construct(
        public int $stage,
        public CarbonImmutable $nextReviewOn,
    ) {}
}
