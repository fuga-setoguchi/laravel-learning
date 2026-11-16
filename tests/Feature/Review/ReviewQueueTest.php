<?php

declare(strict_types=1);

use App\Enums\ReviewMode;
use App\Enums\ReviewResult;
use App\Models\Lesson;
use App\Models\ReviewState;
use App\Review\ReviewQueue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\ReviewFixture;

// docs/tests/LOGIC_test.md 5章（今日 = 2026-12-01）

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-12-01 09:00', 'Asia/Tokyo'));
    $this->today = CarbonImmutable::today();
    $this->queue = app(ReviewQueue::class);
});

it('LOGIC-T18 期限が今日以前の問題が対象で、期限の古い順に出る', function (): void {
    $overdue = ReviewFixture::due('2026-11-30');
    ReviewFixture::due('2026-12-01');
    ReviewFixture::due('2026-12-02');

    expect($this->queue->remaining($this->today)->total)->toBe(2)
        ->and($this->queue->next($this->today)?->question->id)->toBe($overdue->id);
});

it('LOGIC-T19 引退したレッスン・問題は対象にならない', function (): void {
    $retiredQuestion = ReviewFixture::due('2026-12-01');
    $retiredQuestion->update(['retired_at' => now()]);
    $inRetiredLesson = ReviewFixture::due('2026-12-01');
    Lesson::whereKey($inRetiredLesson->lesson_id)->update(['retired_at' => now()]);

    expect($this->queue->remaining($this->today)->total)->toBe(0)
        ->and($this->queue->next($this->today))->toBeNull();
});

it('LOGIC-T20 今日の直前の回答と同じ章を避ける', function (): void {
    $answered = ReviewFixture::due('2026-12-05', chapter: 1);
    ReviewFixture::log($answered, ReviewResult::Remembered, '2026-12-01 08:50');
    ReviewFixture::due('2026-12-01', chapter: 1);
    $chapter2 = ReviewFixture::due('2026-12-01', chapter: 2);

    expect($this->queue->next($this->today)?->question->id)->toBe($chapter2->id);
});

it('LOGIC-T21 前日の回答は、章を避ける判定に使わない', function (): void {
    $answered = ReviewFixture::due('2026-12-05', chapter: 1);
    ReviewFixture::log($answered, ReviewResult::Remembered, '2026-11-30 21:00');
    $chapter1 = ReviewFixture::due('2026-12-01', chapter: 1);
    ReviewFixture::due('2026-12-01', chapter: 2);

    expect($this->queue->next($this->today)?->question->id)->toBe($chapter1->id);
});

it('LOGIC-T22 期限が来た問題がなければ、今日不正解だった問題を古い順に再出題する', function (): void {
    $x = ReviewFixture::failedToday('2026-12-01 08:00');
    ReviewFixture::failedToday('2026-12-01 08:30');

    $item = $this->queue->next($this->today);

    expect($item?->question->id)->toBe($x->id)
        ->and($item?->mode)->toBe(ReviewMode::Practice);
});

it('LOGIC-T23 期限が来た問題があれば、再出題より先に出す', function (): void {
    ReviewFixture::failedToday('2026-12-01 08:00');
    $due = ReviewFixture::due('2026-12-01', chapter: 2);

    $item = $this->queue->next($this->today);

    expect($item?->question->id)->toBe($due->id)
        ->and($item?->mode)->toBe(ReviewMode::Scheduled);
});

it('LOGIC-T23b 再出題でも、直前の回答と同じ章を避ける', function (): void {
    ReviewFixture::failedToday('2026-12-01 08:00', chapter: 1);
    $chapter2 = ReviewFixture::failedToday('2026-12-01 08:10', chapter: 2);
    $passed = ReviewFixture::due('2026-12-05', chapter: 1);
    ReviewFixture::log($passed, ReviewResult::Remembered, '2026-12-01 08:20');

    $item = $this->queue->next($this->today);

    expect($item?->question->id)->toBe($chapter2->id)
        ->and($item?->mode)->toBe(ReviewMode::Practice);
});

it('LOGIC-T24 練習で正解した問題は、再出題の対象から外れる', function (): void {
    $question = ReviewFixture::failedToday('2026-12-01 08:00');
    ReviewFixture::log($question, ReviewResult::Remembered, '2026-12-01 08:10', ReviewMode::Practice);

    expect($this->queue->next($this->today))->toBeNull()
        ->and($this->queue->isPracticeTarget($question->id, $this->today))->toBeFalse();
});

it('LOGIC-T24b 練習でも不正解なら再出題が続き、後ろに回る', function (): void {
    $first = ReviewFixture::failedToday('2026-12-01 08:00');
    $second = ReviewFixture::failedToday('2026-12-01 08:10');
    ReviewFixture::log($first, ReviewResult::Forgot, '2026-12-01 08:20', ReviewMode::Practice);

    expect($this->queue->isPracticeTarget($first->id, $this->today))->toBeTrue()
        ->and($this->queue->next($this->today)?->question->id)->toBe($second->id);
});

it('LOGIC-T24c 想起カードの練習が「あいまい」なら、再出題は終わる', function (): void {
    $question = ReviewFixture::failedToday('2026-12-01 08:00');
    ReviewFixture::log($question, ReviewResult::Vague, '2026-12-01 08:10', ReviewMode::Practice);

    expect($this->queue->isPracticeTarget($question->id, $this->today))->toBeFalse();
});

it('LOGIC-T25 期限の出題で「あいまい」だった問題は、再出題しない', function (): void {
    $question = ReviewFixture::due('2026-12-02');
    ReviewFixture::log($question, ReviewResult::Vague, '2026-12-01 08:00');

    expect($this->queue->isPracticeTarget($question->id, $this->today))->toBeFalse()
        ->and($this->queue->next($this->today))->toBeNull();
});

it('LOGIC-T26 前日に不正解で練習していない問題は、今日は期限の出題として出る', function (): void {
    $question = ReviewFixture::due('2026-12-01');
    ReviewFixture::log($question, ReviewResult::Forgot, '2026-11-30 21:00');

    $item = $this->queue->next($this->today);

    expect($item?->question->id)->toBe($question->id)
        ->and($item?->mode)->toBe(ReviewMode::Scheduled)
        ->and($this->queue->remaining($this->today)->practice)->toBe(0);
});

it('LOGIC-T27 期限が来た問題も再出題もなければ、次の問題はない', function (): void {
    ReviewFixture::due('2026-12-02');

    expect($this->queue->next($this->today))->toBeNull();
});

it('LOGIC-T28 残り件数は、期限が来た問題と再出題の合計（うち再出題）', function (): void {
    foreach (['2026-11-29', '2026-11-30', '2026-12-01'] as $dueOn) {
        ReviewFixture::due($dueOn);
    }
    ReviewFixture::failedToday('2026-12-01 08:00');
    ReviewFixture::failedToday('2026-12-01 08:10');

    $remaining = $this->queue->remaining($this->today);

    expect($remaining->total)->toBe(5)
        ->and($remaining->practice)->toBe(2);
});

it('LOGIC-T29 候補の件数によらず、クエリ数は一定', function (): void {
    $countQueries = function (int $count): int {
        ReviewState::query()->delete();
        for ($i = 0; $i < $count; $i++) {
            ReviewFixture::due('2026-12-01', chapter: $i % 3 + 1);
            ReviewFixture::failedToday('2026-12-01 08:00', chapter: $i % 3 + 1);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->queue->next($this->today);
        $this->queue->remaining($this->today);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    expect($countQueries(50))->toBe($countQueries(5));
});

it('LOGIC-T29b 出題する問題には、レッスン・章・選択肢・段階と最新の回答idが付いている', function (): void {
    $question = ReviewFixture::due('2026-12-01', type: 'choice', stage: 3);
    $log = ReviewFixture::log($question, ReviewResult::Correct, '2026-11-20 09:00');

    $item = $this->queue->next($this->today);

    expect($item?->question->relationLoaded('choices'))->toBeTrue()
        ->and($item?->question->lesson->relationLoaded('chapter'))->toBeTrue()
        ->and($item?->question->reviewState?->stage)->toBe(3)
        ->and($item?->lastLogId)->toBe($log->id);
});
