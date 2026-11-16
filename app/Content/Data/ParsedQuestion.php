<?php

declare(strict_types=1);

namespace App\Content\Data;

use App\Enums\QuestionType;
use App\ValueObjects\Choice;

/** 検証済みの問題（想起カード・選択式・AIコードレビュー問題） */
final readonly class ParsedQuestion
{
    /**
     * @param  list<Choice>  $choices  想起カードは空
     * @param  string|null  $correctKey  正解の選択肢の key。想起カードは null
     */
    public function __construct(
        public string $id,
        public QuestionType $type,
        public int $position,
        public string $prompt,
        public ?string $code,
        public ?string $codeLanguage,
        public ?string $answer,
        public ?string $explanation,
        public array $choices,
        public ?string $correctKey,
    ) {}
}
