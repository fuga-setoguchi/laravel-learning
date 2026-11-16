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
     * 読み出した PreQuestion をモデルにキャッシュしない。キャッシュすると、保存時に値を読んだだけで
     * JSONに書き戻され、jsonb が正規化した文字列と一致しないため「変更あり」と判定されてしまう。
     * PreQuestion は変更できない値オブジェクトなので、キャッシュする必要もない。
     */
    public bool $withoutObjectCaching = true;

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
