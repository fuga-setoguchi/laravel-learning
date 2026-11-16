<?php

declare(strict_types=1);

namespace App\Review;

/** 今日の復習の状態（docs/05_review-logic.md 4.4） */
enum TodayReviewStatus
{
    /** 残りあり */
    case InProgress;

    /** 未登録: 復習に登録済みの有効な問題がない（レッスンをまだ完了していない） */
    case NotStarted;

    /** 完了: 今日の期限の出題に回答し、残りがない */
    case Completed;

    /** 予定なし: 登録はあるが、今日が期限の問題がなく、今日は回答していない */
    case NothingDue;
}
