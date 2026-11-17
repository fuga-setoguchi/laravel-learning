<?php

declare(strict_types=1);

use App\Enums\ReviewResult;
use App\Models\Chapter;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Question;
use App\Models\ReviewState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\ReviewFixture;

// docs/tests/SCR-01_test.md（今日 = 2026-12-01）

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-12-01 09:00', 'Asia/Tokyo'));
});

/** 章 $chapter の $position 番目のレッスンを作る（完了済みにするなら $completed） */
function curriculumLesson(int $chapter, int $position, bool $completed = false, bool $retired = false): Lesson
{
    $chapterModel = Chapter::query()->find($chapter) ?? Chapter::factory()->create(['id' => $chapter]);
    $factory = Lesson::factory()->for($chapterModel);
    $lesson = ($retired ? $factory->retired() : $factory)->create(['position' => $position, 'title' => "レッスン{$chapter}-{$position}"]);
    if ($completed) {
        LessonProgress::factory()->for($lesson)->create();
    }

    return $lesson;
}

it('SCR-01-T01 今日の復習の残り件数（うち再出題）と、復習へのリンクが表示される', function (): void {
    foreach (['2026-11-29', '2026-11-30', '2026-12-01'] as $dueOn) {
        ReviewFixture::due($dueOn);
    }
    ReviewFixture::failedToday('2026-12-01 08:00');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('残り 4 問')
        ->assertSee('（うち再出題 1 問）')
        ->assertSee(route('review.show'))
        ->assertSee('復習を始める');
});

it('SCR-01-T02 今日の期限の問題にすべて回答済みなら、完了と次回の復習日が表示され、開始のリンクはない', function (): void {
    $answered = ReviewFixture::due('2026-12-04');
    ReviewFixture::log($answered, ReviewResult::Remembered, '2026-12-01 08:00');
    ReviewFixture::due('2026-12-08');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('今日の復習は完了しました。')
        ->assertSee('次回の復習: 2026-12-04')
        ->assertDontSee('復習を始める');
});

it('SCR-01-T10 復習に登録済みの問題がなければ、まだ復習する問題がないと表示する', function (): void {
    curriculumLesson(1, 1);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('まだ復習する問題はありません。レッスンを完了すると、翌日から復習が始まります。')
        ->assertDontSee('今日の復習は完了しました')
        ->assertDontSee('次回の復習:');
});

it('SCR-01-T11 登録はあるが今日が期限の問題がなく、今日は回答していなければ、予定なしと次回の復習日を表示する', function (): void {
    ReviewFixture::due('2026-12-02');
    ReviewFixture::due('2026-12-05');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('今日復習する問題はありません。')
        ->assertSee('次回の復習: 2026-12-02')
        ->assertDontSee('今日の復習は完了しました');
});

it('SCR-01-T03 次のレッスンは、未完了のうち章・順番が最も前のもの', function (): void {
    curriculumLesson(1, 1, completed: true);
    curriculumLesson(1, 2, completed: true);
    $next = curriculumLesson(1, 3);
    curriculumLesson(2, 1);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('1-3 レッスン1-3')
        ->assertSee(route('lessons.show', $next))
        ->assertDontSee('2-1 レッスン2-1');
});

it('SCR-01-T04 引退したレッスンは、次のレッスンにならない', function (): void {
    curriculumLesson(1, 3, retired: true);
    $next = curriculumLesson(1, 4);

    $this->get(route('home'))->assertSee(route('lessons.show', $next))->assertDontSee('レッスン1-3');
});

it('SCR-01-T05 レッスンがなければ、取り込みを促す', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('教材がありません。')
        ->assertSee('php artisan lessons:import');
});

it('SCR-01-T06 すべて完了していれば、その旨を表示する', function (): void {
    curriculumLesson(1, 1, completed: true);

    $this->get(route('home'))->assertSee('すべてのレッスンを完了しました。');
});

it('SCR-01-T07 全体の進捗は、有効なレッスンだけで数える', function (): void {
    for ($position = 1; $position <= 10; $position++) {
        curriculumLesson(1, $position, completed: $position <= 3);
    }
    curriculumLesson(1, 11, completed: true, retired: true);
    $question = Question::factory()->for(Lesson::query()->firstOrFail())->create();
    ReviewState::factory()->for($question)->create();

    $this->get(route('home'))
        ->assertSee('全体の進捗: 3 / 10 レッスン完了（30%）')
        ->assertSee('復習に登録済みの問題: 1 問');
});

it('SCR-01-T08 復習が残っていれば、次のレッスンに「復習を終えてから」と添える', function (): void {
    ReviewFixture::due('2026-12-01');

    $this->get(route('home'))->assertSee('復習を終えてから進めましょう');
});

it('SCR-01-T09 レッスン・問題の件数によらず、クエリ数は一定', function (): void {
    $countQueries = function (int $count): int {
        for ($i = 0; $i < $count; $i++) {
            ReviewFixture::due('2026-12-01', chapter: $i % 3 + 1);
            ReviewFixture::failedToday('2026-12-01 08:00');
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('home'))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    expect($countQueries(30))->toBe($countQueries(3));
});
