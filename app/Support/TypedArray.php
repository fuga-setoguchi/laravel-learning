<?php

declare(strict_types=1);

namespace App\Support;

use LogicException;

/**
 * 検証済みの配列から、型を確かめながら値を取り出す。
 * 検証を通った後に型が違うのは実装の誤りのため、LogicException にする。
 */
final class TypedArray
{
    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : throw new LogicException("{$key} が文字列ではありません。");
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return $value === null || is_string($value) ? $value : throw new LogicException("{$key} が文字列ではありません。");
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : throw new LogicException("{$key} が整数ではありません。");
    }

    /**
     * 要素が配列であるリストを取り出す。
     *
     * @param  array<array-key, mixed>  $data
     * @return list<array<array-key, mixed>>
     */
    public static function listOfArrays(array $data, string $key): array
    {
        $value = $data[$key] ?? [];
        if (! is_array($value)) {
            throw new LogicException("{$key} が配列ではありません。");
        }

        return array_values(array_map(
            static fn (mixed $item): array => is_array($item) ? $item : throw new LogicException("{$key} の要素が配列ではありません。"),
            $value,
        ));
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function array(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : throw new LogicException("{$key} が配列ではありません。");
    }
}
