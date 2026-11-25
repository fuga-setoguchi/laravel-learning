<?php

declare(strict_types=1);

use App\Content\SourceLinkChecker;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\ContentFixture;

// docs/tests/CMD-02_test.md
// 本物のサイトには通信せず（Http::preventStrayRequests）、間隔と再試行の待ちも実際には待たない（Sleep::fake）

beforeEach(function (): void {
    $this->content = ContentFixture::create();
    Http::preventStrayRequests();
    Sleep::fake();
});

afterEach(function (): void {
    $this->content->cleanup();
});

/**
 * @return array{code: int, output: string}
 */
function checkSources(string $path): array
{
    $code = Artisan::call('lessons:check-sources', ['--path' => $path]);

    return ['code' => $code, 'output' => Artisan::output()];
}

/** 原稿のURL（docs/commands/CMD-02_lessons-check-sources.md 3章） */
function rawDocsUrl(string $versionAndPage): string
{
    return "https://raw.githubusercontent.com/laravel/docs/{$versionAndPage}.md";
}

/**
 * レッスンの sources（または references）を、指定した URL に置き換える
 *
 * @param  list<string>  $urls
 */
function replaceSources(ContentFixture $content, string $file, array $urls, string $key = 'sources'): void
{
    $content->editLesson($file, function (array $frontMatter) use ($urls, $key): array {
        $frontMatter[$key] = array_map(fn (string $url): array => ['title' => '出典', 'url' => $url], $urls);

        return $frontMatter;
    });
}

it('CMD-02-T01 すべてのファイルとアンカーがあれば、件数を表示して成功する', function (): void {
    replaceSources($this->content, 'ch01/l01.md', ['https://laravel.com/docs/13.x/releases#support-policy']);
    Http::fake(['*' => Http::response('<a name="support-policy"></a>')]);

    $result = checkSources($this->content->path);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('出典を確認しました。')
        ->and($result['output'])->toContain('ファイル: 3 件 / URL: 3 件 / 対象外: 0 件');
});

it('CMD-02-T02 教材のURLから、GitHub の原稿のURLを求めて取得する', function (): void {
    replaceSources($this->content, 'ch01/l01.md', ['https://laravel.com/docs/13.x/queues#max-attempts']);
    replaceSources($this->content, 'ch01/l01.md', ['https://laravel.com/docs/11.x/upgrade'], 'references');
    Http::fake(['*' => Http::response('<a name="max-attempts"></a>')]);

    expect(checkSources($this->content->path)['code'])->toBe(0);

    Http::assertSent(fn (Request $request): bool => $request->url() === rawDocsUrl('13.x/queues'));
    Http::assertSent(fn (Request $request): bool => $request->url() === rawDocsUrl('11.x/upgrade'));
});

it('CMD-02-T03 同じページは、別のアンカーでも1回だけ取得する', function (): void {
    replaceSources($this->content, 'ch01/l01.md', ['https://laravel.com/docs/13.x/queues#max-attempts']);
    replaceSources($this->content, 'ch01/l02.md', [
        'https://laravel.com/docs/13.x/queues#timeout',
        'https://laravel.com/docs/13.x/queues',
    ]);
    Http::fake(['*' => Http::response('<a name="max-attempts"></a><a name="timeout"></a>')]);

    expect(checkSources($this->content->path)['code'])->toBe(0);

    // queues と configuration（ch02/l01）の2ファイル
    Http::assertSentCount(2);
});

it('CMD-02-T04 ファイルが 404 なら、ページが見つからないエラーにし、再試行しない', function (): void {
    Http::fake([
        rawDocsUrl('13.x/lifecycle') => Http::response('Not Found', 404),
        '*' => Http::response('本文'),
    ]);

    $result = checkSources($this->content->path);

    expect($result['code'])->toBe(1)
        ->and($result['output'])->toContain('出典の確認で 1 件のエラーがありました。')
        ->and($result['output'])->not->toContain('。.')
        ->and($result['output'])->toContain('ch01/l02.md')
        ->and($result['output'])->toContain('sources.0.url: ページが見つかりません（https://laravel.com/docs/13.x/lifecycle）');

    Http::assertSentCount(3);
});

it('CMD-02-T05 ファイルにアンカーがなければ、アンカーが見つからないエラーにする', function (): void {
    replaceSources($this->content, 'ch01/l01.md', [
        'https://laravel.com/docs/13.x/releases#support-policy',
        'https://laravel.com/docs/13.x/releases#missing',
    ]);
    Http::fake(['*' => Http::response('<a name="support-policy"></a>')]);

    $result = checkSources($this->content->path);

    expect($result['code'])->toBe(1)
        ->and($result['output'])->toContain('ch01/l01.md')
        ->and($result['output'])->toContain('sources.1.url: アンカーが見つかりません（https://laravel.com/docs/13.x/releases#missing）')
        ->and($result['output'])->not->toContain('#support-policy');
});

it('CMD-02-T06 アンカーのないURLは、ファイルがあればエラーにならない', function (): void {
    Http::fake(['*' => Http::response('アンカーのない本文')]);

    expect(checkSources($this->content->path)['code'])->toBe(0);
});

it('CMD-02-T07 503 が続いた後に成功すれば、再試行して成功する', function (): void {
    Http::fake([
        rawDocsUrl('13.x/lifecycle') => Http::sequence()->pushStatus(503)->pushStatus(503)->push('本文'),
        '*' => Http::response('本文'),
    ]);

    $result = checkSources($this->content->path);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->not->toContain('確認できなかった');

    // lifecycle の3回と、ほかの2ファイル
    Http::assertSentCount(5);
});

it('CMD-02-T08 一時的な失敗が続けば、3回取得した後に警告にする', function (Closure $sequence, string $reason): void {
    Http::fake([
        rawDocsUrl('13.x/lifecycle') => $sequence(),
        '*' => Http::response('本文'),
    ]);

    $result = checkSources($this->content->path);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('確認できなかった URL が 1 件あります（一時的な問題の可能性があります）。')
        ->and($result['output'])->not->toContain('。.')
        ->and($result['output'])->toContain("https://laravel.com/docs/13.x/lifecycle（{$reason}）");

    Http::assertSentCount(5);
})->with([
    '503 が続く' => [fn () => Http::sequence()->pushStatus(503)->pushStatus(503)->pushStatus(503), 'HTTP 503'],
    '接続の失敗が続く' => [fn () => Http::sequence()->pushFailedConnection()->pushFailedConnection()->pushFailedConnection(), '接続できません'],
]);

it('CMD-02-T09 取得の間に 1 秒の待ちを入れる', function (): void {
    Http::fake(['*' => Http::response('本文')]);

    checkSources($this->content->path);

    // 3ファイルの間の2回
    Sleep::assertSequence([
        Sleep::for(1)->second(),
        Sleep::for(1)->second(),
    ]);
});

it('CMD-02-T10 公式ドキュメントでないURLは取得せず、対象外に数える', function (): void {
    replaceSources($this->content, 'ch01/l01.md', ['https://laravel.com/blog/announcing-laravel-13'], 'references');
    Http::fake(['*' => Http::response('本文')]);

    $result = checkSources($this->content->path);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('ファイル: 3 件 / URL: 4 件 / 対象外: 1 件');

    Http::assertSentCount(3);
});

it('CMD-02-T11 教材に検証エラーがあれば、通信せずに失敗する', function (): void {
    $this->content->editLesson('ch01/l01.md', function (array $frontMatter): array {
        unset($frontMatter['title']);

        return $frontMatter;
    });
    Http::fake();

    $result = checkSources($this->content->path);

    expect($result['code'])->toBe(1)
        ->and($result['output'])->toContain('教材の検証で 1 件のエラーがありました。')
        ->and($result['output'])->not->toContain('。.')
        ->and($result['output'])->toContain('title');

    Http::assertNothingSent();
});

it('CMD-02-T12 決めた User-Agent で、認証なしで送る', function (): void {
    Http::fake(['*' => Http::response('本文')]);

    checkSources($this->content->path);

    Http::assertSent(fn (Request $request): bool => $request->header('User-Agent') === [SourceLinkChecker::USER_AGENT]
        && ! $request->hasHeader('Authorization'));
});
