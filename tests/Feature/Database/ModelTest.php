<?php

declare(strict_types=1);

use App\Enums\QuestionType;
use App\Enums\ReviewMode;
use App\Enums\ReviewResult;
use App\Models\Chapter;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\LessonSource;
use App\Models\Question;
use App\Models\QuestionChoice;
use App\Models\ReviewLog;
use App\Models\ReviewState;
use App\ValueObjects\Choice;
use App\ValueObjects\PreQuestion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\MassAssignmentException;

// docs/tests/DB_test.md 2章

it('DB-T08 全モデルをファクトリで作成でき、リレーションをたどれる', function (): void {
    $question = Question::factory()->codeReview()->create();
    $lesson = $question->lesson;
    LessonSource::factory()->for($lesson)->create();
    LessonProgress::factory()->for($lesson)->create();
    ReviewState::factory()->for($question)->create();
    ReviewLog::factory()->for($question)->create();

    $lesson->load(['chapter', 'sources', 'questions.choices', 'questions.reviewState', 'questions.reviewLogs', 'progress']);

    expect($lesson->chapter)->toBeInstanceOf(Chapter::class)
        ->and($lesson->sources)->toHaveCount(1)
        ->and($lesson->progress)->toBeInstanceOf(LessonProgress::class)
        ->and($lesson->questions)->toHaveCount(1);

    $loaded = $lesson->questions->firstOrFail();
    expect($loaded->type)->toBe(QuestionType::CodeReview)
        ->and($loaded->choices->pluck('key')->all())->toBe(['a', 'b', 'c'])
        ->and($loaded->choices->firstOrFail())->toBeInstanceOf(QuestionChoice::class)
        ->and($loaded->reviewState)->toBeInstanceOf(ReviewState::class)
        ->and($loaded->reviewLogs)->toHaveCount(1)
        ->and($loaded->id)->toBe("{$lesson->id}-q{$loaded->position}");
});

it('DB-T09 Lesson::active() は引退していないレッスンだけを返す', function (): void {
    $active = Lesson::factory()->create();
    Lesson::factory()->retired()->create();

    expect(Lesson::active()->pluck('id')->all())->toBe([$active->id]);
});

it('DB-T10 Question::active() は問題とレッスンの両方が引退していない問題だけを返す', function (): void {
    $active = Question::factory()->create();
    Question::factory()->retired()->create();
    Question::factory()->for(Lesson::factory()->retired())->create();

    expect(Question::active()->pluck('id')->all())->toBe([$active->id]);
});

it('DB-T11 事前の問いを保存して読み直すと、同じ値に戻る', function (): void {
    $preQuestion = new PreQuestion(
        prompt: '`new` で生成すると何が困るか',
        choices: [new Choice('a', '遅くなる'), new Choice('b', 'テストで差し替えられない')],
        answer: 'b',
        explanation: '外から差し替える手段がなくなります。',
    );
    $lesson = Lesson::factory()->create(['pre_question' => $preQuestion]);

    expect(Lesson::findOrFail($lesson->id)->pre_question)->toEqual($preQuestion);
});

it('DB-T12 日付の列は Asia/Tokyo の0時の CarbonImmutable として読める', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-12-01 23:30', 'Asia/Tokyo'));
    $state = ReviewState::factory()->due()->create();
    $log = ReviewLog::factory()->create();

    $nextReviewOn = ReviewState::findOrFail($state->question_id)->next_review_on;
    $answeredOn = ReviewLog::findOrFail($log->id)->answered_on;

    foreach ([$nextReviewOn, $answeredOn] as $date) {
        expect($date)->toBeInstanceOf(CarbonImmutable::class)
            ->and($date->format('Y-m-d H:i:s e'))->toBe('2026-12-01 00:00:00 Asia/Tokyo');
    }
});

it('DB-T13 期限の比較で、期限当日の問題が含まれる', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-12-01 09:00', 'Asia/Tokyo'));
    $today = ReviewState::factory()->due(today())->create();
    $yesterday = ReviewState::factory()->due(today()->subDay())->create();
    ReviewState::factory()->notDue()->create();

    $due = ReviewState::where('next_review_on', '<=', today()->toDateString())->pluck('question_id')->sort()->values()->all();

    expect($due)->toBe(collect([$today->question_id, $yesterday->question_id])->sort()->values()->all());
});

it('DB-T14 fillable にない属性をまとめて代入すると例外になる', function (): void {
    expect(fn () => new ReviewLog(['question_id' => 'ch01-l01-c1', 'unknown_column' => 1]))
        ->toThrow(MassAssignmentException::class);
});

it('DB-T15 Enum の values() はすべての値を定義順に返す', function (): void {
    expect(QuestionType::values())->toBe(['recall', 'choice', 'code_review'])
        ->and(ReviewMode::values())->toBe(['scheduled', 'practice'])
        ->and(ReviewResult::values())->toBe(['remembered', 'vague', 'forgot', 'correct', 'incorrect']);
});
