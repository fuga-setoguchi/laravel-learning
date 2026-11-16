<?php

declare(strict_types=1);

namespace App\ValueObjects;

/** 選択肢（教材に書かれた記号と文） */
final readonly class Choice
{
    public function __construct(
        public string $key,
        public string $body,
    ) {}
}
