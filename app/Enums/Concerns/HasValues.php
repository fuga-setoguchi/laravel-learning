<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

trait HasValues
{
    /**
     * すべての case の値を定義順に返す（マイグレーションの CHECK 制約と検証規則で使う）。
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
