<?php

declare(strict_types=1);

use App\Content\LessonFileParser;

// docs/tests/CMD-01_test.md 3章

it('CMD-01-T32 front matter の配列と本文に分かれ、本文の先頭の空行は除かれる', function (): void {
    $result = (new LessonFileParser)->parse("---\nid: ch01-l01\ntitle: タイトル\n---\n\n\n## 概念\n\n本文\n");

    expect($result['frontMatter'])->toBe(['id' => 'ch01-l01', 'title' => 'タイトル'])
        ->and($result['body'])->toBe("## 概念\n\n本文\n");
});

it('CMD-01-T33 本文中の --- は本文として残る', function (): void {
    $result = (new LessonFileParser)->parse("---\nid: ch01-l01\n---\n前半\n\n---\n\n後半\n");

    expect($result['frontMatter'])->toBe(['id' => 'ch01-l01'])
        ->and($result['body'])->toBe("前半\n\n---\n\n後半\n");
});

it('CMD-01-T34 CRLF改行でも LF と同じ結果になる', function (): void {
    $lf = "---\nid: ch01-l01\nsources:\n  - title: A\n---\n\n本文\n1行目\n";

    expect((new LessonFileParser)->parse(str_replace("\n", "\r\n", $lf)))
        ->toBe((new LessonFileParser)->parse($lf));
});
