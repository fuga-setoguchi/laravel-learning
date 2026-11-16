<?php

declare(strict_types=1);

namespace App\Content\Data;

/** 検証済みの章（chapters.yaml の1件） */
final readonly class ParsedChapter
{
    public function __construct(
        public int $number,
        public string $title,
    ) {}
}
