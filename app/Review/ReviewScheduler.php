<?php

declare(strict_types=1);

namespace App\Review;

use App\Enums\Grade;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * 段階と評価から、新しい段階と次回の復習日を決める（docs/05_review-logic.md 3章）。
 * DBと現在時刻に依存しない。今日の日付は呼び出し側から受け取る。
 */
final class ReviewScheduler
{
    /** 段階 => 次回の復習までの日数 */
    public const array INTERVALS = [1 => 1, 2 => 3, 3 => 7, 4 => 14, 5 => 30, 6 => 60];

    private const int MAX_STAGE = 6;

    /** レッスン完了時の初期状態: 段階1、翌日 */
    public function initial(CarbonImmutable $today): ScheduledState
    {
        return $this->schedule(1, $today);
    }

    /** 回答後の状態。次回の復習日は、元の期限日ではなく回答した日（今日）から数える */
    public function next(int $stage, Grade $grade, CarbonImmutable $today): ScheduledState
    {
        if (! isset(self::INTERVALS[$stage])) {
            throw new InvalidArgumentException("段階は1〜6です（{$stage}）。");
        }

        $newStage = match ($grade) {
            Grade::Pass => min($stage + 1, self::MAX_STAGE),
            Grade::Hold => $stage,
            Grade::Fail => 1,
        };

        return $this->schedule($newStage, $today);
    }

    /** 段階 $stage の日数後を次回の復習日とする */
    private function schedule(int $stage, CarbonImmutable $today): ScheduledState
    {
        return new ScheduledState($stage, $today->startOfDay()->addDays(self::INTERVALS[$stage]));
    }
}
