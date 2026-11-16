<?php

declare(strict_types=1);

namespace App\Content;

/** 取り込みの結果（docs/commands/CMD-01_lessons-import.md 5章） */
final readonly class ImportResult
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public ImportCounts $chapters,
        public ImportCounts $lessons,
        public ImportCounts $questions,
        public array $warnings,
        public bool $dryRun,
    ) {}
}
