<?php

declare(strict_types=1);

namespace App\ValueObjects;

use App\Casts\PreQuestionCast;
use Illuminate\Contracts\Database\Eloquent\Castable;
use InvalidArgumentException;

/** 事前の問い（lessons.pre_question。docs/04_content-format.md 3.2） */
final readonly class PreQuestion implements Castable
{
    /**
     * @param  list<Choice>  $choices
     */
    public function __construct(
        public string $prompt,
        public array $choices,
        public string $answer,
        public string $explanation,
    ) {}

    /**
     * @param  array<array-key, mixed>  $arguments
     */
    public static function castUsing(array $arguments): PreQuestionCast
    {
        return new PreQuestionCast;
    }

    /**
     * DBに保存したJSONを復元する。形が想定と違う場合は例外にする。
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $choices = $data['choices'] ?? null;
        if (! is_array($choices)) {
            throw new InvalidArgumentException('pre_question.choices は配列である必要があります。');
        }

        return new self(
            prompt: self::string($data, 'prompt'),
            choices: array_values(array_map(static function (mixed $choice): Choice {
                if (! is_array($choice)) {
                    throw new InvalidArgumentException('pre_question.choices の要素は配列である必要があります。');
                }

                return new Choice(self::string($choice, 'key'), self::string($choice, 'body'));
            }, $choices)),
            answer: self::string($data, 'answer'),
            explanation: self::string($data, 'explanation'),
        );
    }

    /**
     * @return array{prompt: string, choices: list<array{key: string, body: string}>, answer: string, explanation: string}
     */
    public function toArray(): array
    {
        return [
            'prompt' => $this->prompt,
            'choices' => array_map(
                static fn (Choice $choice): array => ['key' => $choice->key, 'body' => $choice->body],
                $this->choices,
            ),
            'answer' => $this->answer,
            'explanation' => $this->explanation,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value)) {
            throw new InvalidArgumentException("pre_question.{$key} は文字列である必要があります。");
        }

        return $value;
    }
}
