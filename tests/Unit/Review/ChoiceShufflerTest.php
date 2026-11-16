<?php

declare(strict_types=1);

use App\Review\ChoiceShuffler;
use Carbon\CarbonImmutable;

// docs/tests/LOGIC_test.md 4章

it('LOGIC-T15 同じ問題・同じ日なら同じ順になる', function (): void {
    $date = CarbonImmutable::parse('2026-12-01', 'Asia/Tokyo');
    $shuffler = new ChoiceShuffler;

    expect($shuffler->shuffle(['a', 'b', 'c', 'd'], 'ch01-l01-q1', $date))
        ->toBe($shuffler->shuffle(['a', 'b', 'c', 'd'], 'ch01-l01-q1', $date->setTime(23, 59)));
});

it('LOGIC-T16 日が変わると順が変わる（10日分で2種類以上）', function (): void {
    $shuffler = new ChoiceShuffler;
    $orders = [];
    for ($day = 0; $day < 10; $day++) {
        $date = CarbonImmutable::parse('2026-12-01', 'Asia/Tokyo')->addDays($day);
        $orders[] = implode(',', $shuffler->shuffle(['a', 'b', 'c'], 'ch01-l01-q1', $date));
    }

    expect(count(array_unique($orders)))->toBeGreaterThanOrEqual(2);
});

it('LOGIC-T17 出力は入力の並べ替えで、要素の欠落・重複がない', function (array $choices): void {
    $shuffled = (new ChoiceShuffler)->shuffle($choices, 'ch01-l01-q1', CarbonImmutable::parse('2026-12-01', 'Asia/Tokyo'));
    sort($shuffled);
    $sorted = $choices;
    sort($sorted);

    expect($shuffled)->toBe($sorted);
})->with([
    '2件' => [['a', 'b']],
    '6件' => [['a', 'b', 'c', 'd', 'e', 'f']],
    '1件' => [['a']],
    '0件' => [[]],
]);
