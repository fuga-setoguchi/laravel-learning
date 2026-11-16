<?php

declare(strict_types=1);

namespace App\Content\Data;

use App\ValueObjects\PreQuestion;

/** 検証済みのレッスン（レッスンファイル1件） */
final readonly class ParsedLesson
{
    /**
     * @param  list<ParsedSource>  $sources
     * @param  list<ParsedQuestion>  $questions  想起カード → 問題の記載順
     */
    public function __construct(
        public string $id,
        public int $chapter,
        public int $position,
        public string $title,
        public string $body,
        public string $laravelVersion,
        public PreQuestion $preQuestion,
        public array $sources,
        public array $questions,
    ) {}
}
