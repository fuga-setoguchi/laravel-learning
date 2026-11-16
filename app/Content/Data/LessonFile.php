<?php

declare(strict_types=1);

namespace App\Content\Data;

/** 教材フォルダから見つけたレッスンファイル */
final readonly class LessonFile
{
    public function __construct(
        /** 表示用のパス（例: content/ch03/l02.md） */
        public string $displayPath,
        /** ファイルの位置から決まるレッスンid（例: ch03-l02） */
        public string $expectedId,
        public int $chapter,
        public int $position,
        public string $contents,
    ) {}
}
