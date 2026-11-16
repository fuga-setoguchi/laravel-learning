<?php

declare(strict_types=1);

namespace App\Casts;

use App\ValueObjects\PreQuestion;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * lessons.pre_question（jsonb）と PreQuestion を相互に変換する。
 *
 * @implements CastsAttributes<PreQuestion, PreQuestion>
 */
final class PreQuestionCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): PreQuestion
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException("{$key} がJSON文字列ではありません。");
        }

        $data = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new InvalidArgumentException("{$key} がJSONのオブジェクトではありません。");
        }

        return PreQuestion::fromArray($data);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (! $value instanceof PreQuestion) {
            throw new InvalidArgumentException("{$key} には PreQuestion を設定してください。");
        }

        return [$key => json_encode($value->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)];
    }
}
