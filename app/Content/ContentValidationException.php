<?php

declare(strict_types=1);

namespace App\Content;

use RuntimeException;

/**
 * 教材の検証エラー。ファイルごとのエラーの一覧を持つ（docs/commands/CMD-01_lessons-import.md 3章）。
 */
final class ContentValidationException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors  表示用のファイルパス => エラーの一覧
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('教材の検証でエラーがありました。');
    }

    public static function single(string $displayPath, string $message): self
    {
        return new self([$displayPath => [$message]]);
    }

    public function count(): int
    {
        return array_sum(array_map(count(...), $this->errors));
    }
}
