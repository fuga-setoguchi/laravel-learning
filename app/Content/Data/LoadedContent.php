<?php

declare(strict_types=1);

namespace App\Content\Data;

/** 教材フォルダから読み込んだ未検証の内容 */
final readonly class LoadedContent
{
    /**
     * @param  list<LessonFile>  $lessonFiles  ファイル名順
     */
    public function __construct(
        public string $chaptersDisplayPath,
        public string $chaptersYaml,
        public array $lessonFiles,
    ) {}
}
