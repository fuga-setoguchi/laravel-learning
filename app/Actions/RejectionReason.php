<?php

declare(strict_types=1);

namespace App\Actions;

/** 回答を記録しなかった理由（docs/05_review-logic.md 7.2） */
enum RejectionReason: string
{
    /** 引退した問題、または復習に登録されていない問題 */
    case NotTarget = 'not_target';

    /** 期限前（回答済みで次回の復習日が先になった。二重送信・戻るボタン） */
    case AlreadyAnswered = 'already_answered';

    /** 再出題の対象でない（今日不正解になっていない、練習で正解済み） */
    case PracticeFinished = 'practice_finished';

    /** 画面を表示した後に、同じ問題の回答が記録されている（二重送信・戻るボタン） */
    case StaleForm = 'stale_form';

    public function message(): string
    {
        $message = __("review.rejected.{$this->value}");

        return is_string($message) ? $message : $this->value;
    }
}
