<?php

declare(strict_types=1);

namespace App\Review;

/** 次に出題する問題の候補 */
final readonly class Candidate
{
    public function __construct(
        public string $questionId,
        public int $chapter,
    ) {}
}
