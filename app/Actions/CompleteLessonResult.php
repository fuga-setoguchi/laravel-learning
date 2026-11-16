<?php

declare(strict_types=1);

namespace App\Actions;

/** レッスン完了の結果 */
final readonly class CompleteLessonResult
{
    public function __construct(
        /** 新たに復習に登録した問題の件数（再受講で登録済みなら0） */
        public int $registeredCount,
        /** 初めての完了か */
        public bool $firstCompletion,
    ) {}
}
