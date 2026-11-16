<?php

declare(strict_types=1);

namespace App\Content\Data;

/** 検証済みの出典 */
final readonly class ParsedSource
{
    public function __construct(
        public string $title,
        public string $url,
    ) {}
}
