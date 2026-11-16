<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\QuestionType;
use App\Enums\ReviewMode;
use App\Enums\ReviewResult;
use App\Models\Question;
use App\Models\QuestionChoice;
use App\Models\ReviewLog;
use App\Models\ReviewState;
use App\Review\ReviewQueue;
use App\Review\ReviewScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 復習の回答を受け付けてよいか判定し、記録する（docs/05_review-logic.md 7章）。
 * 古い画面からの送信・二重送信は記録せず、理由つきの Rejected を返す。
 */
final readonly class RecordReviewAnswer
{
    private const array RATINGS = [
        ReviewResult::Remembered->value,
        ReviewResult::Vague->value,
        ReviewResult::Forgot->value,
    ];

    public function __construct(
        private ReviewScheduler $scheduler,
        private ReviewQueue $queue,
    ) {}

    /**
     * @throws ValidationException 問題の種別と送信された項目が合わない場合
     */
    public function handle(SubmittedAnswer $answer, CarbonImmutable $now): Recorded|Rejected
    {
        $question = Question::query()->with('choices')->findOrFail($answer->questionId);

        // (2) 種別と入力の組み合わせ。受け付けの判定より先に行う
        $result = $this->result($question, $answer);
        $today = $now->startOfDay();

        // (3) 受け付けの判定と記録。状態を読み直してから判定する
        return DB::transaction(function () use ($question, $answer, $result, $now, $today): Recorded|Rejected {
            $isActive = Question::query()->active()->whereKey($question->id)->exists();
            $state = ReviewState::query()->find($question->id);
            if (! $isActive || $state === null) {
                return new Rejected(RejectionReason::NotTarget);
            }

            if ($answer->mode === ReviewMode::Scheduled && $state->next_review_on->greaterThan($today)) {
                return new Rejected(RejectionReason::AlreadyAnswered);
            }

            if ($answer->mode === ReviewMode::Practice && ! $this->queue->isPracticeTarget($question->id, $today)) {
                return new Rejected(RejectionReason::PracticeFinished);
            }

            if ($this->queue->lastLogId($question->id) !== $answer->lastLogId) {
                return new Rejected(RejectionReason::StaleForm);
            }

            return $answer->mode === ReviewMode::Scheduled
                ? $this->recordScheduled($state, $answer, $result, $now, $today)
                : new Recorded($this->createLog($answer, $result, $now, null, null));
        });
    }

    /** 期限による出題: 段階と次回の復習日を更新して記録する */
    private function recordScheduled(ReviewState $state, SubmittedAnswer $answer, ReviewResult $result, CarbonImmutable $now, CarbonImmutable $today): Recorded|Rejected
    {
        $next = $this->scheduler->next($state->stage, $result->grade(), $today);

        // 期限が来ていることを条件に更新し、更新件数で最終判定する（同時に送信されても二重に記録しない）
        $updated = ReviewState::query()
            ->whereKey($state->question_id)
            ->where('next_review_on', '<=', $today->toDateString())
            ->update([
                'stage' => $next->stage,
                'next_review_on' => $next->nextReviewOn->toDateString(),
                'last_reviewed_at' => $now,
            ]);
        if ($updated === 0) {
            return new Rejected(RejectionReason::AlreadyAnswered);
        }

        return new Recorded($this->createLog($answer, $result, $now, $state->stage, $next->stage));
    }

    private function createLog(SubmittedAnswer $answer, ReviewResult $result, CarbonImmutable $now, ?int $stageBefore, ?int $stageAfter): ReviewLog
    {
        return ReviewLog::query()->create([
            'question_id' => $answer->questionId,
            'mode' => $answer->mode,
            'result' => $result,
            'choice_key' => $answer->choiceKey,
            'stage_before' => $stageBefore,
            'stage_after' => $stageAfter,
            'answered_at' => $now,
            'answered_on' => $now->startOfDay(),
        ]);
    }

    /**
     * 問題の種別と送信された項目から結果を決める
     *
     * @throws ValidationException
     */
    private function result(Question $question, SubmittedAnswer $answer): ReviewResult
    {
        if ($question->type === QuestionType::Recall) {
            if ($answer->choiceKey !== null) {
                throw ValidationException::withMessages(['choice_key' => $this->message('unexpected_input')]);
            }
            if ($answer->rating === null || ! in_array($answer->rating, self::RATINGS, true)) {
                throw ValidationException::withMessages(['rating' => $this->message('rating_required')]);
            }

            return ReviewResult::from($answer->rating);
        }

        if ($answer->rating !== null) {
            throw ValidationException::withMessages(['rating' => $this->message('unexpected_input')]);
        }
        if ($answer->choiceKey === null) {
            throw ValidationException::withMessages(['choice_key' => $this->message('choice_required')]);
        }

        $chosen = $question->choices->first(static fn (QuestionChoice $choice): bool => $choice->key === $answer->choiceKey);
        if ($chosen === null) {
            throw ValidationException::withMessages(['choice_key' => $this->message('choice_unknown')]);
        }

        return $chosen->is_correct ? ReviewResult::Correct : ReviewResult::Incorrect;
    }

    private function message(string $key): string
    {
        $message = __("review.invalid.{$key}");

        return is_string($message) ? $message : $key;
    }
}
