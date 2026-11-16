<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ReviewMode;

/** 復習の画面から送信された回答（形式の検証済み） */
final readonly class SubmittedAnswer
{
    public function __construct(
        public string $questionId,
        public ReviewMode $mode,
        /** 画面を表示した時点での、その問題の最新の review_logs.id（なければ0） */
        public int $lastLogId,
        /** 想起カードの自己評価（remembered / vague / forgot） */
        public ?string $rating,
        /** 選択式・AIコードレビュー問題で選んだ選択肢の key */
        public ?string $choiceKey,
    ) {}
}
