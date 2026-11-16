<?php

declare(strict_types=1);

use App\Actions\CompleteLesson;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Question;
use App\Models\ReviewState;
use Carbon\CarbonImmutable;

// docs/tests/LOGIC_test.md 6章

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-12-01 09:00', 'Asia/Tokyo'));
    $this->lesson = Lesson::factory()->create();
});

/** レッスンに想起カード3件・選択式1件・AIコードレビュー1件（計5件）を作る */
function createFiveQuestions(Lesson $lesson): void
{
    Question::factory()->count(3)->for($lesson)->create();
    Question::factory()->choice()->for($lesson)->create();
    Question::factory()->codeReview()->for($lesson)->create();
}

it('LOGIC-T30 未完了のレッスンを完了すると、完了の記録と5件の復習（段階1・翌日）が作られる', function (): void {
    createFiveQuestions($this->lesson);

    $result = app(CompleteLesson::class)->handle($this->lesson, CarbonImmutable::now());

    expect($result->registeredCount)->toBe(5)
        ->and($result->firstCompletion)->toBeTrue()
        ->and(LessonProgress::findOrFail($this->lesson->id)->completed_at->toDateTimeString())->toBe('2026-12-01 09:00:00')
        ->and(ReviewState::count())->toBe(5)
        ->and(ReviewState::where('stage', 1)->whereDate('next_review_on', '2026-12-02')->count())->toBe(5);
});

it('LOGIC-T31 完了済みのレッスンを再度完了しても、完了日時と登録済みの段階は変わらない', function (): void {
    createFiveQuestions($this->lesson);
    app(CompleteLesson::class)->handle($this->lesson, CarbonImmutable::now());
    ReviewState::query()->update(['stage' => 3, 'next_review_on' => '2026-12-08']);
    $before = ReviewState::orderBy('question_id')->get()->toArray();

    $this->travelTo(CarbonImmutable::parse('2026-12-05 10:00', 'Asia/Tokyo'));
    $result = app(CompleteLesson::class)->handle($this->lesson, CarbonImmutable::now());

    expect($result->registeredCount)->toBe(0)
        ->and($result->firstCompletion)->toBeFalse()
        ->and(LessonProgress::findOrFail($this->lesson->id)->completed_at->toDateTimeString())->toBe('2026-12-01 09:00:00')
        ->and(ReviewState::orderBy('question_id')->get()->toArray())->toBe($before);
});

it('LOGIC-T32 完了後に追加された問題だけが、再度の完了で段階1・翌日で登録される', function (): void {
    createFiveQuestions($this->lesson);
    app(CompleteLesson::class)->handle($this->lesson, CarbonImmutable::now());
    $added = Question::factory()->for($this->lesson)->create();

    $this->travelTo(CarbonImmutable::parse('2026-12-05 10:00', 'Asia/Tokyo'));
    $result = app(CompleteLesson::class)->handle($this->lesson, CarbonImmutable::now());

    expect($result->registeredCount)->toBe(1)
        ->and(ReviewState::findOrFail($added->id)->stage)->toBe(1)
        ->and(ReviewState::findOrFail($added->id)->next_review_on->toDateString())->toBe('2026-12-06')
        ->and(ReviewState::count())->toBe(6);
});

it('LOGIC-T33 引退した問題は復習に登録されない', function (): void {
    Question::factory()->count(2)->for($this->lesson)->create();
    $retired = Question::factory()->retired()->for($this->lesson)->create();

    $result = app(CompleteLesson::class)->handle($this->lesson, CarbonImmutable::now());

    expect($result->registeredCount)->toBe(2)
        ->and(ReviewState::find($retired->id))->toBeNull();
});
