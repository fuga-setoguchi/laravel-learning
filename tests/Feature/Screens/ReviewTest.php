<?php

declare(strict_types=1);

use App\Enums\ReviewMode;
use App\Enums\ReviewResult;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\ReviewLog;
use App\Models\ReviewState;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Testing\TestResponse;
use Tests\Support\EnablesCsrfProtection;
use Tests\Support\ReviewFixture;

// docs/tests/SCR-04_test.md（今日 = 2026-12-01）

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-12-01 09:00', 'Asia/Tokyo'));
});

/**
 * 出題画面に表示されている問題に回答する（hidden の値は画面から読む）
 *
 * @param  array<string, string>  $input  rating または choice_key
 */
function submitShown(TestResponse $page, array $input): TestResponse
{
    $html = (string) $page->getContent();
    preg_match_all('/<input type="hidden" name="(question_id|mode|last_log_id)" value="([^"]*)">/', $html, $matches, PREG_SET_ORDER);
    $hidden = [];
    foreach ($matches as $match) {
        $hidden[$match[1]] = $match[2];
    }

    return test()->post(route('review.answers.store'), [...$hidden, ...$input]);
}

it('SCR-04-T01 期限が来た想起カードは、問題文と <details> 内の答え・3つの評価ボタンで出題される', function (): void {
    $question = ReviewFixture::due('2026-12-01');
    $question->update(['prompt' => '想起カードの問い', 'answer' => '想起カードの答え']);

    $this->get(route('review.show'))
        ->assertOk()
        ->assertSee('残り 1 問')
        ->assertSee('想起カードの問い')
        ->assertSeeInOrder(['<details>', '想起カードの答え', 'value="remembered"', 'value="vague"', 'value="forgot"', '</details>'], false)
        ->assertSee('<input type="hidden" name="question_id" value="'.$question->id.'">', false)
        ->assertSee('<input type="hidden" name="mode" value="scheduled">', false)
        ->assertSee('<input type="hidden" name="last_log_id" value="0">', false)
        ->assertDontSee('再出題（練習）');
});

it('SCR-04-T02 期限が来たAIコードレビュー問題は、問題文・コード・選択肢で出題される', function (): void {
    ReviewFixture::due('2026-12-01', type: 'code_review');

    $this->get(route('review.show'))
        ->assertOk()
        ->assertSee('<code class="language-php">', false)
        ->assertSee('new PaymentService()', false)
        ->assertSee('type="radio" name="choice_key" value="a"', false)
        ->assertSee('type="radio" name="choice_key" value="b"', false)
        ->assertSee('回答する');
});

it('SCR-04-T03 想起カードに回答すると、次の問題が表示される', function (): void {
    $first = ReviewFixture::due('2026-11-30');
    $second = ReviewFixture::due('2026-12-01');

    submitShown($this->get(route('review.show')), ['rating' => 'remembered'])
        ->assertRedirect(route('review.show'));

    $this->get(route('review.show'))
        ->assertSee('value="'.$second->id.'"', false)
        ->assertDontSee('value="'.$first->id.'"', false);
});

it('SCR-04-T04 選択式に正解すると、正誤と解説と次回の復習日の画面に移る', function (): void {
    $question = ReviewFixture::due('2026-12-01', type: 'choice');
    $question->update(['explanation' => '選択式の解説']);

    $response = submitShown($this->get(route('review.show')), ['choice_key' => 'a']);
    $log = ReviewLog::sole();
    $response->assertRedirect(route('review.answers.show', $log));

    $this->get(route('review.answers.show', $log))
        ->assertOk()
        ->assertSee('正解 ✓')
        ->assertSee('選択式の解説')
        ->assertSee('次回の復習: 2026-12-04（段階 2）')
        ->assertDontSee('正解するまで今日の最後に出題します');
});

it('SCR-04-T05 選択式に不正解だと、あなたの答えと正解、再出題の案内が表示される', function (): void {
    ReviewFixture::due('2026-12-01', type: 'choice');

    submitShown($this->get(route('review.show')), ['choice_key' => 'b']);

    $this->get(route('review.answers.show', ReviewLog::sole()))
        ->assertOk()
        ->assertSee('不正解 ✗')
        ->assertSee('← あなたの答え')
        ->assertSee('← 正解')
        ->assertSee('次回の復習: 2026-12-02（段階 1）')
        ->assertSee('不正解の問題は、正解するまで今日の最後に出題します。');
});

it('SCR-04-T06/T07 不正解の問題は、期限が来た問題の後に再出題され、正解するまで続く', function (): void {
    $choice = ReviewFixture::due('2026-11-30', type: 'choice');
    ReviewFixture::due('2026-12-01');

    // 期限の出題で不正解
    submitShown($this->get(route('review.show')), ['choice_key' => 'b']);
    // 残りの期限が来た問題に回答
    submitShown($this->get(route('review.show')), ['rating' => 'remembered']);

    // T06: 再出題（練習）として出る
    $practice = $this->get(route('review.show'))
        ->assertSee('再出題（練習）')
        ->assertSee('<input type="hidden" name="question_id" value="'.$choice->id.'">', false)
        ->assertSee('<input type="hidden" name="mode" value="practice">', false)
        ->assertSee('残り 1 問')
        ->assertSee('（うち再出題 1 問）');

    // T07: 練習でも不正解 → もう一度出る → 正解 → 完了
    submitShown($practice, ['choice_key' => 'c']);
    $this->get(route('review.answers.show', ReviewLog::latest('id')->firstOrFail()))
        ->assertDontSee('次回の復習:');
    $again = $this->get(route('review.show'))->assertSee('再出題（練習）');
    submitShown($again, ['choice_key' => 'a']);

    $this->get(route('review.show'))->assertSee('今日の復習は完了しました');
    expect(ReviewState::findOrFail($choice->id)->stage)->toBe(1);
});

it('SCR-04-T08 回答済みの問題に同じ画面から再送信しても記録しない', function (): void {
    ReviewFixture::due('2026-12-01');
    $page = $this->get(route('review.show'));
    submitShown($page, ['rating' => 'remembered']);

    submitShown($page, ['rating' => 'remembered'])
        ->assertRedirect(route('review.show'))
        ->assertSessionHas('status', 'この問題はすでに回答済みのため、記録しませんでした。');
    expect(ReviewLog::count())->toBe(1);
});

it('SCR-04-T09 練習で不正解になった直後に同じ画面から再送信しても記録しない', function (): void {
    ReviewFixture::failedToday('2026-12-01 08:00');
    $page = $this->get(route('review.show'))->assertSee('再出題（練習）');
    submitShown($page, ['rating' => 'forgot']);

    submitShown($page, ['rating' => 'forgot'])
        ->assertSessionHas('status', 'この画面の後に回答が記録されているため、記録しませんでした。');
    expect(ReviewLog::count())->toBe(2);
});

it('SCR-04-T10 形式が不正な送信は、検証エラーで直前の画面に戻り記録しない', function (array $input): void {
    $question = ReviewFixture::due('2026-12-01');

    $this->from(route('review.show'))
        ->post(route('review.answers.store'), [
            'question_id' => $question->id, 'mode' => 'scheduled', 'last_log_id' => 0, ...$input,
        ])
        ->assertRedirect(route('review.show'))
        ->assertSessionHasErrors();
    expect(ReviewLog::count())->toBe(0);
})->with([
    '想起カードに選択肢だけ' => [['choice_key' => 'a']],
    '出題の種類が不正' => [['mode' => 'extra', 'rating' => 'remembered']],
    '存在しない問題' => [['question_id' => 'ch99-l99-c1', 'rating' => 'remembered']],
]);

it('SCR-04-T11 CSRFトークンなしの回答は拒否される', function (): void {
    $this->app->bind(PreventRequestForgery::class, EnablesCsrfProtection::class);
    $question = ReviewFixture::due('2026-12-01');

    $this->post(route('review.answers.store'), [
        'question_id' => $question->id, 'mode' => 'scheduled', 'last_log_id' => 0, 'rating' => 'remembered',
    ])->assertStatus(419);
    expect(ReviewLog::count())->toBe(0);
});

it('SCR-04-T12/T13 結果画面は再読み込みしても同じで、後の回答で次回の復習日が変わらない', function (): void {
    $question = ReviewFixture::due('2026-12-01', type: 'choice');
    submitShown($this->get(route('review.show')), ['choice_key' => 'a']);
    $log = ReviewLog::sole();
    $first = $this->get(route('review.answers.show', $log))->assertSee('次回の復習: 2026-12-04（段階 2）');

    // T12: 再読み込み
    expect((string) $this->get(route('review.answers.show', $log))->getContent())->toBe((string) $first->getContent())
        ->and(ReviewLog::count())->toBe(1);

    // T13: 後日、同じ問題にまた回答した後でも、古い結果画面は回答時点の値のまま
    $this->travelTo(CarbonImmutable::parse('2026-12-04 09:00', 'Asia/Tokyo'));
    submitShown($this->get(route('review.show')), ['choice_key' => 'a']);
    expect(ReviewState::findOrFail($question->id)->stage)->toBe(3);

    $this->get(route('review.answers.show', $log))->assertSee('次回の復習: 2026-12-04（段階 2）');
});

it('SCR-04-T14 想起カードの回答の結果画面は404', function (): void {
    $question = ReviewFixture::due('2026-12-05');
    $log = ReviewFixture::log($question, ReviewResult::Remembered, '2026-12-01 08:00');

    $this->get(route('review.answers.show', $log))->assertNotFound();
});

it('SCR-04-T15 問題がなければ、完了画面に今日の集計と次のレッスンが表示される', function (): void {
    foreach ([ReviewResult::Remembered, ReviewResult::Correct, ReviewResult::Vague] as $result) {
        ReviewFixture::log(ReviewFixture::due('2026-12-05'), $result, '2026-12-01 08:00');
    }
    // 不正解だった問題は、練習で正解済み（再出題が終わっている）。練習の回答は集計に含めない
    $failed = ReviewFixture::failedToday('2026-12-01 08:10');
    ReviewFixture::log($failed, ReviewResult::Remembered, '2026-12-01 08:30', ReviewMode::Practice);
    // 問題を作るときに作ったレッスンは完了済みにして、第5章のレッスンを「次のレッスン」にする
    Lesson::query()->each(fn (Lesson $lesson) => LessonProgress::factory()->for($lesson)->create());
    $next = ReviewFixture::lesson(5);
    $next->update(['title' => '次に受講するレッスン']);

    $this->get(route('review.show'))
        ->assertOk()
        ->assertSee('今日の復習は完了しました')
        ->assertSee('今日の回答: 4 問')
        ->assertSee('正解・思い出せた 2 / あいまい 1 / 不正解・思い出せなかった 1')
        ->assertSee('次のレッスン:')
        ->assertSee('次に受講するレッスン');
});

it('SCR-04-T15 今日回答した問題がなければ、その旨を表示する', function (): void {
    $this->get(route('review.show'))
        ->assertOk()
        ->assertSee('今日復習する問題はありませんでした。');
});

it('SCR-04-T16 途中で再読み込みしても、続きの同じ問題が表示される', function (): void {
    ReviewFixture::due('2026-11-29');
    $second = ReviewFixture::due('2026-11-30');
    ReviewFixture::due('2026-12-01');
    submitShown($this->get(route('review.show')), ['rating' => 'remembered']);

    foreach ([1, 2] as $_) {
        $this->get(route('review.show'))->assertSee('<input type="hidden" name="question_id" value="'.$second->id.'">', false);
    }
});

it('SCR-04-T17 出題画面にレッスンへのリンクはない', function (): void {
    $question = ReviewFixture::due('2026-12-01');

    $this->get(route('review.show'))
        ->assertOk()
        ->assertSee($question->lesson->title)
        ->assertDontSee(route('lessons.show', $question->lesson_id));
});

it('SCR-04-T18 引退した問題への回答は記録しない', function (): void {
    $question = ReviewFixture::due('2026-12-01');
    $page = $this->get(route('review.show'));
    $question->update(['retired_at' => now()]);

    submitShown($page, ['rating' => 'remembered'])
        ->assertSessionHas('status', 'この問題は復習の対象ではないため、記録しませんでした。');
    expect(ReviewLog::count())->toBe(0);
});

it('SCR-04-T19 AIコードレビュー問題のコードはエスケープして表示する', function (): void {
    $question = ReviewFixture::due('2026-12-01', type: 'code_review');
    $question->update(['code' => '<script>alert("xss")</script>']);

    $this->get(route('review.show'))
        ->assertOk()
        ->assertDontSee('<script>alert("xss")</script>', false)
        ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);
});
