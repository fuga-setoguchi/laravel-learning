<?php

declare(strict_types=1);

namespace App\Review;

use App\Enums\ReviewMode;
use App\Models\Question;

/** 次に出題する問題 */
final readonly class ReviewItem
{
    public function __construct(
        /** lesson.chapter・choices・reviewState を読み込み済み */
        public Question $question,
        public ReviewMode $mode,
        /** その問題の最新の review_logs.id（なければ0）。画面の hidden で送り、二重送信を見分ける */
        public int $lastLogId,
    ) {}
}
