<?php

declare(strict_types=1);

namespace App\Review;

use Carbon\CarbonImmutable;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * 選択肢の表示順を決める（docs/05_review-logic.md 5章）。
 * 同じ日・同じ問題なら同じ順（再読み込みで変わらない）、日が変われば別の順になるよう、
 * 乱数ではなく「id + 日付」をシードにした決定的なシャッフルにする。
 */
final class ChoiceShuffler
{
    /**
     * @template T
     *
     * @param  list<T>  $choices
     * @param  string  $id  問題id（事前の問いはレッスンid）
     * @return list<T>
     */
    public function shuffle(array $choices, string $id, CarbonImmutable $date): array
    {
        $seed = crc32($id.$date->toDateString());

        return (new Randomizer(new Mt19937($seed)))->shuffleArray($choices);
    }
}
