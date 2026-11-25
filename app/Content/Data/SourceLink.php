<?php

declare(strict_types=1);

namespace App\Content\Data;

/** 実在を確かめる出典URL1件と、それが書かれている場所（CMD-02） */
final readonly class SourceLink
{
    public function __construct(
        /** 表示用のファイルパス（例: content/ch03/l02.md） */
        public string $displayPath,
        /** 項目のパス（例: sources.0.url） */
        public string $field,
        public string $url,
        /** URL の # 以降。なければ null */
        public ?string $anchor,
    ) {}
}
