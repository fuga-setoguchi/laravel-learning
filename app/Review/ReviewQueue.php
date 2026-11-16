<?php

declare(strict_types=1);

namespace App\Review;

use App\Enums\Grade;
use App\Enums\ReviewMode;
use App\Enums\ReviewResult;
use App\Models\Question;
use App\Models\ReviewLog;
use App\Support\TypedArray;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * DBの状態から、次に出題する問題と残り件数を決める（docs/05_review-logic.md 4章）。
 * 出題順はセッションに持たず、呼ばれるたびに決め直す（途中で閉じても再開できるように）。
 * クエリ数は件数によらず一定にする（期限が来た問題・今日の回答をそれぞれ1回で取得する）。
 */
final readonly class ReviewQueue
{
    public function __construct(private NextQuestionPicker $picker) {}

    public function next(CarbonImmutable $today): ?ReviewItem
    {
        [$due, $practice, $lastChapter] = $this->candidates($today);

        // 期限が来た問題（A）を先に出し、なくなったら再出題（B）
        [$candidates, $mode] = $due !== [] ? [$due, ReviewMode::Scheduled] : [$practice, ReviewMode::Practice];
        $picked = $this->picker->pick($candidates, $lastChapter);
        if ($picked === null) {
            return null;
        }

        $question = Question::query()
            ->with(['lesson.chapter', 'choices', 'reviewState'])
            ->findOrFail($picked->questionId);

        return new ReviewItem($question, $mode, $this->lastLogId($question->id));
    }

    public function remaining(CarbonImmutable $today): RemainingCount
    {
        [$due, $practice] = $this->candidates($today);

        return new RemainingCount(count($due) + count($practice), count($practice));
    }

    /** 今、再出題（B）の対象か */
    public function isPracticeTarget(string $questionId, CarbonImmutable $today): bool
    {
        [, $practice] = $this->candidates($today);

        foreach ($practice as $candidate) {
            if ($candidate->questionId === $questionId) {
                return true;
            }
        }

        return false;
    }

    /** その問題の最新の回答の id（なければ0） */
    public function lastLogId(string $questionId): int
    {
        $id = ReviewLog::query()->where('question_id', $questionId)->max('id');

        return is_numeric($id) ? (int) $id : 0;
    }

    /**
     * @return array{0: list<Candidate>, 1: list<Candidate>, 2: int|null} 期限が来た問題（A）、再出題（B）、直前に回答した問題の章
     */
    private function candidates(CarbonImmutable $today): array
    {
        $due = $this->dueCandidates($today);
        [$practice, $lastChapter] = $this->practiceCandidates($today, $due);

        return [$due, $practice, $lastChapter];
    }

    /**
     * A. 期限が来た問題（期限切れを含む）。期限の古い順 → 章・レッスン・問題の順
     *
     * @return list<Candidate>
     */
    private function dueCandidates(CarbonImmutable $today): array
    {
        return array_values(DB::table('review_states')
            ->join('questions', 'questions.id', '=', 'review_states.question_id')
            ->join('lessons', 'lessons.id', '=', 'questions.lesson_id')
            ->whereNull('questions.retired_at')
            ->whereNull('lessons.retired_at')
            ->where('review_states.next_review_on', '<=', $today->toDateString())
            ->orderBy('review_states.next_review_on')
            ->orderBy('lessons.chapter_id')
            ->orderBy('lessons.position')
            ->orderBy('questions.position')
            ->get(['review_states.question_id', 'lessons.chapter_id'])
            ->map(static fn (object $row): Candidate => new Candidate(
                TypedArray::string((array) $row, 'question_id'),
                TypedArray::int((array) $row, 'chapter_id'),
            ))
            ->all());
    }

    /**
     * B. 再出題の対象: 今日 scheduled で fail があり、今日の最新の回答（練習を含む）も fail の問題。
     * 今日の最新の回答の古い順（直前に間違えた問題は後ろに回る）。
     *
     * @param  list<Candidate>  $due
     * @return array{0: list<Candidate>, 1: int|null} 再出題の候補、直前に回答した問題の章
     */
    private function practiceCandidates(CarbonImmutable $today, array $due): array
    {
        $logs = DB::table('review_logs')
            ->join('questions', 'questions.id', '=', 'review_logs.question_id')
            ->join('lessons', 'lessons.id', '=', 'questions.lesson_id')
            ->where('review_logs.answered_on', $today->toDateString())
            ->orderBy('review_logs.answered_at')
            ->orderBy('review_logs.id')
            ->get([
                'review_logs.question_id',
                'review_logs.mode',
                'review_logs.result',
                'lessons.chapter_id',
                'questions.retired_at as question_retired_at',
                'lessons.retired_at as lesson_retired_at',
            ])
            ->map(static fn (object $row): array => (array) $row);

        /** @var array<string, array{chapter: int, active: bool, scheduledFail: bool, latestFail: bool}> $questions 問題id => 今日の回答のまとめ（最新の回答の順に並ぶ） */
        $questions = [];
        foreach ($logs as $log) {
            $id = TypedArray::string($log, 'question_id');
            $failed = ReviewResult::from(TypedArray::string($log, 'result'))->grade() === Grade::Fail;
            $scheduled = TypedArray::string($log, 'mode') === ReviewMode::Scheduled->value;
            $previous = $questions[$id] ?? null;
            unset($questions[$id]);

            $questions[$id] = [
                'chapter' => TypedArray::int($log, 'chapter_id'),
                'active' => $log['question_retired_at'] === null && $log['lesson_retired_at'] === null,
                'scheduledFail' => ($previous['scheduledFail'] ?? false) || ($scheduled && $failed),
                'latestFail' => $failed,
            ];
        }

        $dueIds = array_map(static fn (Candidate $candidate): string => $candidate->questionId, $due);
        $practice = [];
        foreach ($questions as $id => $question) {
            if ($question['active'] && $question['scheduledFail'] && $question['latestFail'] && ! in_array($id, $dueIds, true)) {
                $practice[] = new Candidate($id, $question['chapter']);
            }
        }

        $last = $logs->last();

        return [$practice, $last === null ? null : TypedArray::int($last, 'chapter_id')];
    }
}
