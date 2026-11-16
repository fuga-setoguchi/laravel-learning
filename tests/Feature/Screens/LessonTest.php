<?php

declare(strict_types=1);

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\LessonSource;
use App\Models\Question;
use App\Models\ReviewState;
use App\ValueObjects\Choice;
use App\ValueObjects\PreQuestion;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\Support\EnablesCsrfProtection;

// docs/tests/SCR-03_test.md

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-12-01 09:00', 'Asia/Tokyo'));

    $this->lesson = Lesson::factory()->create([
        'title' => 'なぜコントローラーでnewせずに注入するのか',
        'body' => "## 概念\n\n本文の内容です。\n",
        'pre_question' => new PreQuestion(
            prompt: '事前の問いの文',
            choices: [new Choice('a', '処理が遅くなる'), new Choice('b', 'テストで差し替えられない'), new Choice('c', '文法エラーになる')],
            answer: 'b',
            explanation: '事前の問いの解説',
        ),
    ]);
    LessonSource::factory()->for($this->lesson)->create(['title' => 'Service Container', 'url' => 'https://laravel.com/docs/13.x/container']);
    Question::factory()->count(3)->sequence(
        ['prompt' => '想起カード1', 'answer' => '答え1'],
        ['prompt' => '想起カード2', 'answer' => '答え2'],
        ['prompt' => '想起カード3', 'answer' => '答え3'],
    )->for($this->lesson)->create();
    Question::factory()->choice()->for($this->lesson)->create(['prompt' => '選択式の問題文']);
    Question::factory()->codeReview()->for($this->lesson)->create(['prompt' => 'コードレビューの問題文']);
});

it('SCR-03-T01 事前の問いが表示され、本文と「答えずに進む」手段はない', function (): void {
    $this->get(route('lessons.show', $this->lesson))
        ->assertOk()
        ->assertSee('事前の問いの文')
        ->assertSee('テストで差し替えられない')
        ->assertSee('method="GET" action="'.route('lessons.read', $this->lesson).'"', false)
        ->assertSee('aria-current="step"', false)
        ->assertDontSee('本文の内容です')
        ->assertDontSee('本文へ進む');
});

it('SCR-03-T02 存在しない・引退したレッスンはすべての段階で404', function (string $method, string $route): void {
    $retired = Lesson::factory()->retired()->create();
    LessonProgress::factory()->for($retired)->create();

    foreach (['ch99-l99', $retired->id] as $id) {
        $this->call($method, route($route, ['lesson' => $id, 'answer' => 'a']))->assertNotFound();
    }
})->with([
    ['GET', 'lessons.show'],
    ['GET', 'lessons.read'],
    ['GET', 'lessons.recall'],
    ['POST', 'lessons.complete'],
    ['GET', 'lessons.completed'],
]);

it('SCR-03-T03 事前の問いに正解すると「正解」と解説、本文、対象バージョン、出典が表示される', function (): void {
    $this->get(route('lessons.read', [$this->lesson, 'answer' => 'b']))
        ->assertOk()
        ->assertSee('正解 ✓')
        ->assertSee('事前の問いの解説')
        ->assertSee('本文の内容です')
        ->assertSee('Laravel 13.x')
        ->assertSee('<a href="https://laravel.com/docs/13.x/container" target="_blank" rel="noopener noreferrer">Service Container</a>', false)
        ->assertSee(route('lessons.recall', $this->lesson));
});

it('SCR-03-T04 事前の問いに不正解だと「不正解」、あなたの答えと正解が表示される', function (): void {
    $this->get(route('lessons.read', [$this->lesson, 'answer' => 'a']))
        ->assertOk()
        ->assertSee('不正解 ✗')
        ->assertSee('あなたの答え: 処理が遅くなる')
        ->assertSee('正解: テストで差し替えられない');
});

it('SCR-03-T05 事前の問いに答えずに本文を開くと、事前の問いに戻される', function (array $query): void {
    $this->get(route('lessons.read', [$this->lesson, ...$query]))
        ->assertRedirect(route('lessons.show', $this->lesson));
})->with([
    'answer なし' => [[]],
    '選択肢にない値' => [['answer' => 'z']],
]);

it('SCR-03-T06 教材のHTMLはエスケープされ、javascript: のリンクは出力されない', function (): void {
    $this->lesson->update([
        'body' => "<script>alert('body')</script>\n\n[危険なリンク](javascript:alert(1))\n",
    ]);
    Question::where('lesson_id', $this->lesson->id)->where('prompt', '想起カード1')->update(['prompt' => '<script>alert("card")</script>']);

    $this->get(route('lessons.read', [$this->lesson, 'answer' => 'b']))
        ->assertOk()
        ->assertDontSee("<script>alert('body')</script>", false)
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee('javascript:', false);

    $this->get(route('lessons.recall', $this->lesson))
        ->assertOk()
        ->assertDontSee('<script>alert("card")</script>', false);
});

it('SCR-03-T07 思い出す画面では、想起カードの問いと <details> 内の答えだけが表示される', function (): void {
    $response = $this->get(route('lessons.recall', $this->lesson))->assertOk();

    $response->assertSeeInOrder(['想起カード1', '想起カード2', '想起カード3'])
        ->assertSee('<summary>答えを見る</summary>', false)
        ->assertSee('答え1')
        ->assertDontSee('本文の内容です')
        ->assertDontSee('Service Container')
        ->assertDontSee('選択式の問題文')
        ->assertDontSee('コードレビューの問題文')
        ->assertSee('action="'.route('lessons.complete', $this->lesson).'"', false);
});

it('SCR-03-T08 引退した想起カードは表示されない', function (): void {
    Question::factory()->retired()->for($this->lesson)->create(['prompt' => '引退したカード']);

    $this->get(route('lessons.recall', $this->lesson))
        ->assertOk()
        ->assertDontSee('引退したカード');
});

it('SCR-03-T09 完了すると完了画面へリダイレクトし、登録した件数が表示される', function (): void {
    $this->post(route('lessons.complete', $this->lesson))
        ->assertRedirect(route('lessons.completed', $this->lesson))
        ->assertSessionHas('registered_count', 5);

    $this->get(route('lessons.completed', $this->lesson))
        ->assertOk()
        ->assertSee('5 問を復習に登録しました。最初の復習は明日です。');

    expect(ReviewState::count())->toBe(5);
});

it('SCR-03-T10 完了を2回送信しても、DBは1回目と同じで、完了画面は「すでに登録」と表示する', function (): void {
    $this->post(route('lessons.complete', $this->lesson));
    $before = ReviewState::orderBy('question_id')->get()->toArray();

    $this->followingRedirects()
        ->post(route('lessons.complete', $this->lesson))
        ->assertOk()
        ->assertSee('このレッスンの問題はすでに復習に登録されています。');

    expect(ReviewState::orderBy('question_id')->get()->toArray())->toBe($before)
        ->and(LessonProgress::count())->toBe(1);
});

it('SCR-03-T11 CSRFトークンなしの完了の送信は拒否される', function (): void {
    $this->app->bind(PreventRequestForgery::class, EnablesCsrfProtection::class);

    $this->post(route('lessons.complete', $this->lesson))->assertStatus(419);

    expect(LessonProgress::count())->toBe(0)
        ->and(ReviewState::count())->toBe(0);
});

it('SCR-03-T12 完了済みのレッスンの完了画面を直接開くと、件数の文はなく、ホームと次のレッスンが表示される', function (): void {
    LessonProgress::factory()->for($this->lesson)->create();
    $next = Lesson::factory()->create(['chapter_id' => $this->lesson->chapter_id, 'title' => '次に受講するレッスン']);

    $this->get(route('lessons.completed', $this->lesson))
        ->assertOk()
        ->assertSee('レッスンを完了しました')
        ->assertDontSee('復習に登録しました')
        ->assertDontSee('すでに復習に登録されています')
        ->assertSee(route('home'))
        ->assertSee('次のレッスン:')
        ->assertSee(route('lessons.show', $next))
        ->assertSee('次に受講するレッスン');
});

it('SCR-03-T13 未完了のレッスンの完了画面を開くと、事前の問いに戻される', function (): void {
    $this->get(route('lessons.completed', $this->lesson))
        ->assertRedirect(route('lessons.show', $this->lesson));
});

it('SCR-03-T14 同じ日に2回表示すると、事前の問いの選択肢は同じ順で表示される', function (): void {
    // 画面に出てくる順に、選択肢の文を並べる
    $order = function (): array {
        $html = (string) $this->get(route('lessons.show', $this->lesson))->getContent();

        return collect(['処理が遅くなる', 'テストで差し替えられない', '文法エラーになる'])
            ->sortBy(fn (string $body): int => (int) mb_strpos($html, $body))
            ->values()
            ->all();
    };

    $morning = $order();
    $this->travelTo(CarbonImmutable::parse('2026-12-01 23:00', 'Asia/Tokyo'));

    expect($order())->toBe($morning);
});
