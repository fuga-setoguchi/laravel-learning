<?php

declare(strict_types=1);

use App\Models\Chapter;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Question;
use App\Models\ReviewState;
use Illuminate\Support\Facades\DB;

// docs/tests/SCR-02_test.md

/** 章 $chapter の $position 番目のレッスンを作る */
function chapterLesson(int $chapter, int $position, bool $completed = false, bool $retired = false): Lesson
{
    $chapterModel = Chapter::query()->find($chapter) ?? Chapter::factory()->create(['id' => $chapter, 'title' => "章タイトル{$chapter}"]);
    $factory = Lesson::factory()->for($chapterModel);
    $lesson = ($retired ? $factory->retired() : $factory)->create(['position' => $position, 'title' => "レッスン{$chapter}-{$position}"]);
    if ($completed) {
        LessonProgress::factory()->for($lesson)->create();
    }

    return $lesson;
}

it('SCR-02-T01 章は番号順、レッスンは順番順に、受講へのリンク付きで表示される', function (): void {
    $lesson22 = chapterLesson(2, 2);
    chapterLesson(2, 1);
    chapterLesson(1, 1);

    $this->get(route('curriculum'))
        ->assertOk()
        ->assertSeeInOrder(['第1章 章タイトル1', '1-1 レッスン1-1', '第2章 章タイトル2', '2-1 レッスン2-1', '2-2 レッスン2-2'])
        ->assertSee(route('lessons.show', $lesson22));
});

it('SCR-02-T02 章ごとの進捗と、完了したレッスンの「完了」が表示される', function (): void {
    foreach ([1, 2, 3, 4] as $position) {
        chapterLesson(1, $position, completed: $position <= 3);
    }

    $this->get(route('curriculum'))
        ->assertSee('3 / 4（75%）')
        ->assertSeeInOrder(['完了', 'レッスン1-1', '完了', 'レッスン1-2', '完了', 'レッスン1-3', 'レッスン1-4']);
});

it('SCR-02-T03 レッスンがない章は「準備中」', function (): void {
    Chapter::factory()->create(['id' => 3, 'title' => '章タイトル3']);

    $this->get(route('curriculum'))->assertSee('第3章 章タイトル3')->assertSee('準備中');
});

it('SCR-02-T03b 引退した章は表示されない', function (): void {
    Chapter::factory()->retired()->create(['id' => 4, 'title' => '消した章']);

    $this->get(route('curriculum'))->assertOk()->assertDontSee('消した章');
});

it('SCR-02-T04 引退したレッスンは表示されず、進捗の分母にも含まれない', function (): void {
    chapterLesson(1, 1, completed: true);
    chapterLesson(1, 2, completed: true, retired: true);

    $this->get(route('curriculum'))
        ->assertDontSee('レッスン1-2')
        ->assertSee('1 / 1（100%）');
});

it('SCR-02-T05/T06 完了済みのレッスンにだけ、未登録の問題の件数が表示される', function (): void {
    $completed = chapterLesson(1, 1, completed: true);
    Question::factory()->count(2)->for($completed)->create();
    $registered = Question::factory()->for($completed)->create();
    ReviewState::factory()->for($registered)->create();
    Question::factory()->retired()->for($completed)->create();

    $notCompleted = chapterLesson(1, 2);
    Question::factory()->count(3)->for($notCompleted)->create();

    $this->get(route('curriculum'))
        ->assertSee('未登録の問題 2')
        ->assertDontSee('未登録の問題 3');
});

it('SCR-02-T07 章・レッスンの件数によらず、クエリ数は一定', function (): void {
    $countQueries = function (int $chapters): int {
        for ($chapter = 1; $chapter <= $chapters; $chapter++) {
            foreach ([1, 2, 3] as $position) {
                $lesson = chapterLesson($chapter + 10 * $chapters, $position, completed: $position === 1);
                Question::factory()->for($lesson)->create();
            }
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('curriculum'))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    expect($countQueries(6))->toBe($countQueries(2));
});
