<?php

declare(strict_types=1);

use App\Models\Chapter;
use App\Models\Lesson;
use App\Models\LessonSource;
use App\Models\Question;
use App\Models\QuestionChoice;
use App\Models\ReviewState;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// docs/tests/DB_test.md 1章

/**
 * 制約違反になるはずの処理を、セーブポイント（入れ子のトランザクション）の中で実行する。
 * PostgreSQL ではトランザクション内でエラーが起きると、以降のクエリがすべて拒否されるため、
 * RefreshDatabase のトランザクションを壊さないよう、失敗した処理だけを巻き戻す。
 */
function inSavepoint(Closure $callback): Closure
{
    return fn () => DB::transaction($callback);
}

it('DB-T01 アプリの8つのテーブルがあり、Laravel標準の不要なテーブルはない', function (): void {
    $tables = [
        'chapters', 'lessons', 'lesson_sources', 'questions', 'question_choices',
        'lesson_progress', 'review_states', 'review_logs',
    ];
    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("{$table} がない");
    }

    foreach (['users', 'sessions', 'cache', 'jobs'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse("{$table} がある");
    }
});

it('DB-T02 段階は1〜6だけを保存できる', function (int $stage, bool $valid): void {
    $question = Question::factory()->create();
    $insert = fn () => DB::table('review_states')->insert([
        'question_id' => $question->id,
        'stage' => $stage,
        'next_review_on' => '2026-12-01',
    ]);

    if ($valid) {
        $insert();
        expect(ReviewState::count())->toBe(1);
    } else {
        expect(inSavepoint($insert))->toThrow(QueryException::class, 'review_states_stage_check');
    }
})->with([
    '0' => [0, false],
    '1' => [1, true],
    '6' => [6, true],
    '7' => [7, false],
]);

it('DB-T03 列挙値以外の種別・出題の種類・結果は保存できない', function (string $table, array $values): void {
    $question = Question::factory()->create();
    $rows = [
        'questions' => [
            'id' => 'ch99-l99-c1', 'lesson_id' => $question->lesson_id, 'position' => 99, 'prompt' => 'x',
        ],
        'review_logs' => [
            'question_id' => $question->id, 'mode' => 'scheduled', 'result' => 'remembered',
            'answered_at' => '2026-12-01 09:00:00', 'answered_on' => '2026-12-01',
        ],
    ];

    expect(inSavepoint(fn () => DB::table($table)->insert([...$rows[$table], ...$values])))
        ->toThrow(QueryException::class, 'check');
})->with([
    'questions.type' => ['questions', ['type' => 'essay']],
    'review_logs.mode' => ['review_logs', ['mode' => 'extra']],
    'review_logs.result' => ['review_logs', ['result' => 'perfect']],
]);

it('DB-T04 同じ章・順番のレッスン、同じ問題・記号の選択肢は保存できない', function (): void {
    $lesson = Lesson::factory()->create();
    expect(inSavepoint(fn () => Lesson::factory()->create([
        'id' => 'ch99-l99',
        'chapter_id' => $lesson->chapter_id,
        'position' => $lesson->position,
    ])))->toThrow(QueryException::class, 'unique');

    $question = Question::factory()->choice()->create();
    expect(inSavepoint(fn () => QuestionChoice::factory()->for($question)->create(['key' => 'a'])))
        ->toThrow(QueryException::class, 'unique');
});

it('DB-T05 存在しない問題の復習状態は保存できない', function (): void {
    expect(inSavepoint(fn () => ReviewState::factory()->create(['question_id' => 'ch99-l99-c1'])))
        ->toThrow(QueryException::class, 'foreign key');
});

it('DB-T06 復習状態がある問題は削除できない（学習記録を巻き込まない）', function (): void {
    $state = ReviewState::factory()->create();

    expect(inSavepoint(fn () => DB::table('questions')->where('id', $state->question_id)->delete()))
        ->toThrow(QueryException::class, 'foreign key');
    expect(ReviewState::count())->toBe(1);
});

it('DB-T16 lesson_sources の種別は source / reference だけを保存できる', function (): void {
    $source = LessonSource::factory()->create();

    expect(inSavepoint(fn () => DB::table('lesson_sources')->where('id', $source->id)->update(['kind' => 'other'])))
        ->toThrow(QueryException::class, 'check');
});

it('DB-T07 レッスン・問題を削除すると、出典・選択肢も削除される', function (): void {
    $source = LessonSource::factory()->create();
    $question = Question::factory()->choice()->create();
    expect(QuestionChoice::where('question_id', $question->id)->count())->toBe(3);

    DB::table('lessons')->where('id', $source->lesson_id)->delete();
    DB::table('questions')->where('id', $question->id)->delete();

    expect(LessonSource::count())->toBe(0)
        ->and(QuestionChoice::count())->toBe(0)
        ->and(Chapter::count())->toBe(2);
});
