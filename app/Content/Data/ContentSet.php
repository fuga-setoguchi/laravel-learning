<?php

declare(strict_types=1);

namespace App\Content\Data;

/** 検証済みの教材一式。取り込み（ContentImporter）はこれだけを受け取り、配列を扱わない */
final readonly class ContentSet
{
    /**
     * @param  list<ParsedChapter>  $chapters
     * @param  list<ParsedLesson>  $lessons
     * @param  list<string>  $warnings
     */
    public function __construct(
        public array $chapters,
        public array $lessons,
        public array $warnings,
    ) {}
}
