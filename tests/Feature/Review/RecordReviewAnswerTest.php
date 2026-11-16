<?php

declare(strict_types=1);

use App\Actions\Recorded;
use App\Actions\RecordReviewAnswer;
use App\Actions\Rejected;
use App\Actions\RejectionReason;
use App\Actions\SubmittedAnswer;
use App\Enums\ReviewMode;
use App\Enums\ReviewResult;
use App\Models\Question;
use App\Models\ReviewLog;
use App\Models\ReviewState;
use App\Review\ReviewQueue;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\Support\ReviewFixture;

// docs/tests/LOGIC_test.md 7章（今日 = 2026-12-01）

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-12-01 09:00', 'Asia/Tokyo'));
});

/**
 * 画面を表示した時点の last_log_id で回答する（$lastLogId を指定しなければ、今の最新の回答のid）
 */
function answer(Question $question, ReviewMode $mode = ReviewMode::Scheduled, ?string $rating = null, ?string $choiceKey = null, ?int $lastLogId = null): Recorded|Rejected
{
    return app(RecordReviewAnswer::class)->handle(
        new SubmittedAnswer(
            questionId: $question->id,
            mode: $mode,
            lastLogId: $lastLogId ?? app(ReviewQueue::class)->lastLogId($question->id),
            rating: $rating,
            choiceKey: $choiceKey,
        ),
        CarbonImmutable::now(),
    );
}

function expectRejected(Recorded|Rejected $result, RejectionReason $reason): void
{
    expect($result)->toBeInstanceOf(Rejected::class)
        ->and($result instanceof Rejected ? $result->reason : null)->toBe($reason);
}

it('LOGIC-T34 期限が来た想起カード（段階2）に「思い出せた」と回答すると、段階3・7日後になる', function (): void {
    $question = ReviewFixture::due('2026-12-01', stage: 2);

    $result = answer($question, rating: 'remembered');

    expect($result)->toBeInstanceOf(Recorded::class);
    $log = ReviewLog::sole();
    expect($log->mode)->toBe(ReviewMode::Scheduled)
        ->and($log->result)->toBe(ReviewResult::Remembered)
        ->and([$log->stage_before, $log->stage_after])->toBe([2, 3])
        ->and($log->answered_on->toDateString())->toBe('2026-12-01');

    $state = ReviewState::findOrFail($question->id);
    expect($state->stage)->toBe(3)
        ->and($state->next_review_on->toDateString())->toBe('2026-12-08')
        ->and($state->last_reviewed_at?->toDateTimeString())->toBe('2026-12-01 09:00:00');
});

it('LOGIC-T35 選択式に正解すると correct で段階が上がる', function (): void {
    $question = ReviewFixture::due('2026-12-01', type: 'choice');

    answer($question, choiceKey: 'a');

    expect(ReviewLog::sole()->result)->toBe(ReviewResult::Correct)
        ->and(ReviewState::findOrFail($question->id)->stage)->toBe(2);
});

it('LOGIC-T36 選択式に不正解だと incorrect で段階1・翌日になり、選んだ選択肢が保存される', function (): void {
    $question = ReviewFixture::due('2026-12-01', type: 'code_review', stage: 4);

    answer($question, choiceKey: 'b');

    $log = ReviewLog::sole();
    $state = ReviewState::findOrFail($question->id);
    expect($log->result)->toBe(ReviewResult::Incorrect)
        ->and($log->choice_key)->toBe('b')
        ->and($state->stage)->toBe(1)
        ->and($state->next_review_on->toDateString())->toBe('2026-12-02');
});

it('LOGIC-T37 期限前の問題への回答は記録しない', function (): void {
    $question = ReviewFixture::due('2026-12-02');

    expectRejected(answer($question, rating: 'remembered'), RejectionReason::AlreadyAnswered);
    expect(ReviewLog::count())->toBe(0)
        ->and(ReviewState::findOrFail($question->id)->stage)->toBe(1);
});

it('LOGIC-T38 同じ画面からの二重送信は、2回目を記録しない', function (): void {
    $question = ReviewFixture::due('2026-12-01');

    answer($question, rating: 'remembered', lastLogId: 0);
    expectRejected(answer($question, rating: 'remembered', lastLogId: 0), RejectionReason::AlreadyAnswered);

    expect(ReviewLog::count())->toBe(1);
});

it('LOGIC-T39 今日不正解だった問題への練習の回答は、段階を変えずに記録する', function (): void {
    $question = ReviewFixture::failedToday('2026-12-01 08:00');
    $before = ReviewState::findOrFail($question->id)->toArray();

    $result = answer($question, ReviewMode::Practice, rating: 'remembered');

    expect($result)->toBeInstanceOf(Recorded::class);
    $log = ReviewLog::latest('id')->firstOrFail();
    expect($log->mode)->toBe(ReviewMode::Practice)
        ->and([$log->stage_before, $log->stage_after])->toBe([null, null])
        ->and(ReviewState::findOrFail($question->id)->toArray())->toBe($before);
});

it('LOGIC-T40 練習で正解済みの問題に、もう一度練習の回答をしても記録しない', function (): void {
    $question = ReviewFixture::failedToday('2026-12-01 08:00');
    answer($question, ReviewMode::Practice, rating: 'remembered');

    expectRejected(answer($question, ReviewMode::Practice, rating: 'remembered'), RejectionReason::PracticeFinished);
    expect(ReviewLog::count())->toBe(2);
});

it('LOGIC-T40b 練習で不正解の直後に同じ画面から再送信しても記録しない', function (): void {
    $question = ReviewFixture::failedToday('2026-12-01 08:00');
    $shown = app(ReviewQueue::class)->lastLogId($question->id);
    answer($question, ReviewMode::Practice, rating: 'forgot', lastLogId: $shown);

    expectRejected(answer($question, ReviewMode::Practice, rating: 'forgot', lastLogId: $shown), RejectionReason::StaleForm);
    expect(ReviewLog::count())->toBe(2);
});

it('LOGIC-T40c 練習の不正解は何回でも続けられ、正解すると記録される', function (): void {
    $question = ReviewFixture::failedToday('2026-12-01 08:00');
    answer($question, ReviewMode::Practice, rating: 'forgot');
    answer($question, ReviewMode::Practice, rating: 'forgot');

    expect(answer($question, ReviewMode::Practice, rating: 'remembered'))->toBeInstanceOf(Recorded::class)
        ->and(ReviewLog::count())->toBe(4);
});

it('LOGIC-T41 今日不正解になっていない問題に、練習の回答をしても記録しない', function (): void {
    $question = ReviewFixture::due('2026-12-05');

    expectRejected(answer($question, ReviewMode::Practice, rating: 'remembered'), RejectionReason::PracticeFinished);
    expect(ReviewLog::count())->toBe(0);
});

it('LOGIC-T42 不正解の直後に戻るボタンで期限の出題として再送信しても、練習として記録しない', function (): void {
    $question = ReviewFixture::due('2026-12-01');
    answer($question, rating: 'forgot', lastLogId: 0);

    expectRejected(answer($question, rating: 'remembered', lastLogId: 0), RejectionReason::AlreadyAnswered);
    expect(ReviewLog::count())->toBe(1);
});

it('LOGIC-T43 引退した問題・復習に登録されていない問題への回答は記録しない', function (): void {
    $retired = ReviewFixture::due('2026-12-01');
    $retired->update(['retired_at' => now()]);
    $unregistered = ReviewFixture::question();

    expectRejected(answer($retired, rating: 'remembered'), RejectionReason::NotTarget);
    expectRejected(answer($unregistered, rating: 'remembered'), RejectionReason::NotTarget);
    expect(ReviewLog::count())->toBe(0);
});

it('LOGIC-T44 問題の種別と送信された項目が合わなければ、検証エラーで記録しない', function (string $type, ?string $rating, ?string $choiceKey): void {
    $question = ReviewFixture::due('2026-12-01', type: $type);

    expect(fn () => answer($question, rating: $rating, choiceKey: $choiceKey))->toThrow(ValidationException::class);
    expect(ReviewLog::count())->toBe(0);
})->with([
    '想起カードに選択肢' => ['recall', null, 'a'],
    '想起カードに評価なし' => ['recall', null, null],
    '選択式に評価' => ['choice', 'remembered', null],
    '選択式に存在しない選択肢' => ['choice', null, 'f'],
]);

it('LOGIC-T45 期限切れの問題に正解すると、次回の復習日は回答日から数える', function (): void {
    $question = ReviewFixture::due('2026-11-25', stage: 4);

    answer($question, rating: 'remembered');

    expect(ReviewState::findOrFail($question->id)->next_review_on->toDateString())->toBe('2026-12-31');
});

it('LOGIC-T46 23:59 に表示した問題に 0:01 に回答すると、回答した日を基準に記録する', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-12-01 23:59', 'Asia/Tokyo'));
    $question = ReviewFixture::due('2026-12-01');
    $shown = app(ReviewQueue::class)->lastLogId($question->id);

    $this->travelTo(CarbonImmutable::parse('2026-12-02 00:01', 'Asia/Tokyo'));
    $result = answer($question, rating: 'remembered', lastLogId: $shown);

    expect($result)->toBeInstanceOf(Recorded::class)
        ->and(ReviewLog::sole()->answered_on->toDateString())->toBe('2026-12-02')
        ->and(ReviewState::findOrFail($question->id)->next_review_on->toDateString())->toBe('2026-12-05');
});

it('LOGIC-T47 前日に不正解だった問題の練習の回答は、日をまたぐと記録しない', function (): void {
    $question = ReviewFixture::failedToday('2026-12-01 21:00');

    $this->travelTo(CarbonImmutable::parse('2026-12-02 00:01', 'Asia/Tokyo'));

    expectRejected(answer($question, ReviewMode::Practice, rating: 'remembered'), RejectionReason::PracticeFinished);
});

it('LOGIC-T48 形式の不正は、期限前かどうかより先に検証する', function (): void {
    $question = ReviewFixture::due('2026-12-05');

    expect(fn () => answer($question, choiceKey: 'a'))->toThrow(ValidationException::class);
});
