<?php

declare(strict_types=1);

namespace App\Content;

use App\Content\Data\ContentSet;
use App\Content\Data\ParsedSource;
use App\Content\Data\SourceLink;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * CMD-02 出典URLのページとアンカーの実在確認（docs/commands/CMD-02_lessons-check-sources.md）。
 * laravel.com ではなく、公式ドキュメントの原稿（GitHub の laravel/docs）を読んで確かめる（3章）。
 * 相手のサービスに負担をかけないよう、同じファイルは1回だけ、1件ずつ間をあけて取得する（4章）。
 */
final class SourceLinkChecker
{
    public const string USER_AGENT = 'laravel-learning-source-check';

    /** https://laravel.com/docs/{バージョン}/{ページ}#{アンカー}（アンカーは省略できる） */
    private const string DOCS_URL_PATTERN = '~\Ahttps://laravel\.com/docs/([^/#?]+)/([^/#?]+)(?:#(.+))?\z~';

    private const int INTERVAL_SECONDS = 1;

    private const int TIMEOUT_SECONDS = 10;

    /** 最初の1回と、再試行の2回 */
    private const int MAX_ATTEMPTS = 3;

    private const int RETRY_SLEEP_MILLISECONDS = 5000;

    /**
     * @param  array<string, string>  $displayPaths  レッスンid => 表示用のファイルパス
     */
    public function check(ContentSet $content, array $displayPaths): SourceCheckResult
    {
        /** @var array<string, list<SourceLink>> $linksByFile 原稿のURL => そのファイルで確かめる出典 */
        $linksByFile = [];
        /** @var array<string, string> $pages 原稿のURL => 表示用のページのURL */
        $pages = [];
        $urlCount = 0;
        $skippedCount = 0;

        foreach ($content->lessons as $lesson) {
            $displayPath = $displayPaths[$lesson->id] ?? $lesson->id;

            foreach (['sources' => $lesson->sources, 'references' => $lesson->references] as $key => $sources) {
                foreach ($sources as $index => $source) {
                    $urlCount++;
                    $target = $this->target($source);
                    if ($target === null) {
                        $skippedCount++;

                        continue;
                    }

                    [$fileUrl, $page, $anchor] = $target;
                    $pages[$fileUrl] = $page;
                    $linksByFile[$fileUrl][] = new SourceLink($displayPath, "{$key}.{$index}.url", $source->url, $anchor);
                }
            }
        }

        $errors = [];
        $warnings = [];
        $first = true;

        foreach ($linksByFile as $fileUrl => $links) {
            if (! $first) {
                Sleep::for(self::INTERVAL_SECONDS)->seconds();
            }
            $first = false;

            $response = $this->fetch($fileUrl);

            if (is_string($response)) {
                $warnings[] = __('content.source_check.unavailable', ['url' => $pages[$fileUrl], 'reason' => $response]);

                continue;
            }

            if ($response->status() === 404) {
                foreach ($links as $link) {
                    $errors[$link->displayPath][] = "{$link->field}: ".__('content.source_check.page_missing', ['url' => $link->url]);
                }

                continue;
            }

            if ($response->status() !== 200) {
                $reason = __('content.source_check.http_status', ['status' => $response->status()]);
                $warnings[] = __('content.source_check.unavailable', ['url' => $pages[$fileUrl], 'reason' => $reason]);

                continue;
            }

            $body = $response->body();
            foreach ($links as $link) {
                if ($link->anchor !== null && ! str_contains($body, "<a name=\"{$link->anchor}\">")) {
                    $errors[$link->displayPath][] = "{$link->field}: ".__('content.source_check.anchor_missing', ['url' => $link->url]);
                }
            }
        }

        ksort($errors);

        return new SourceCheckResult($errors, $warnings, count($linksByFile), $urlCount, $skippedCount);
    }

    /**
     * 出典URLから、確認に読む原稿のURL・表示用のページのURL・アンカーを求める。公式ドキュメントのURLでなければ null
     *
     * @return array{0: string, 1: string, 2: string|null}|null
     */
    private function target(ParsedSource $source): ?array
    {
        if (preg_match(self::DOCS_URL_PATTERN, $source->url, $matches) !== 1) {
            return null;
        }

        [, $version, $page] = $matches;
        $base = rtrim(config()->string('learning.docs_source_base'), '/');

        return [
            "{$base}/{$version}/{$page}.md",
            "https://laravel.com/docs/{$version}/{$page}",
            $matches[3] ?? null,
        ];
    }

    /**
     * 原稿を取得する。応答があれば Response、接続できなければその理由を返す
     */
    private function fetch(string $url): Response|string
    {
        try {
            return Http::withUserAgent(self::USER_AGENT)
                ->timeout(self::TIMEOUT_SECONDS)
                ->retry(self::MAX_ATTEMPTS, self::RETRY_SLEEP_MILLISECONDS, $this->isTemporaryFailure(...), throw: false)
                ->get($url);
        } catch (ConnectionException) {
            return __('content.source_check.connection_failed');
        }
    }

    /** 再試行する失敗か（接続の失敗・タイムアウト・429・5xx）。404 などは再試行しない */
    private function isTemporaryFailure(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && ($exception->response->status() === 429 || $exception->response->serverError());
    }
}
