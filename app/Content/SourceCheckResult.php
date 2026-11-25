<?php

declare(strict_types=1);

namespace App\Content;

/** 出典リンクの実在確認の結果（CMD-02） */
final readonly class SourceCheckResult
{
    /**
     * @param  array<string, list<string>>  $errors  表示用のファイルパス => エラーの一覧
     * @param  list<string>  $warnings  確認できなかったページ
     */
    public function __construct(
        public array $errors,
        public array $warnings,
        public int $fileCount,
        public int $urlCount,
        public int $skippedCount,
    ) {}

    public function errorCount(): int
    {
        return array_sum(array_map(count(...), $this->errors));
    }
}
