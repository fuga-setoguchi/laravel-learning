<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Question;
use App\Models\ReviewState;
use App\Review\ReviewScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * レッスンを完了し、問題を復習に登録する（docs/05_review-logic.md 6章）。
 * 何度実行しても、完了日時と登録済みの問題の段階は変わらない（再受講・二重送信に備える）。
 */
final readonly class CompleteLesson
{
    public function __construct(private ReviewScheduler $scheduler) {}

    public function handle(Lesson $lesson, CarbonImmutable $now): CompleteLessonResult
    {
        return DB::transaction(function () use ($lesson, $now): CompleteLessonResult {
            $progress = LessonProgress::query()->firstOrCreate(
                ['lesson_id' => $lesson->id],
                ['completed_at' => $now],
            );

            // 有効な問題のうち、まだ復習に登録していないものだけを「段階1・翌日」で登録する
            $initial = $this->scheduler->initial($now);
            $questionIds = Question::query()
                ->active()
                ->where('lesson_id', $lesson->id)
                ->whereDoesntHave('reviewState')
                ->orderBy('position')
                ->pluck('id');

            foreach ($questionIds as $questionId) {
                ReviewState::query()->create([
                    'question_id' => $questionId,
                    'stage' => $initial->stage,
                    'next_review_on' => $initial->nextReviewOn,
                ]);
            }

            return new CompleteLessonResult($questionIds->count(), $progress->wasRecentlyCreated);
        });
    }
}
