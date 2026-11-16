<?php

declare(strict_types=1);

use App\Review\Candidate;
use App\Review\NextQuestionPicker;

// docs/tests/LOGIC_test.md 3章。候補は（id, 章）で表す

/**
 * @param  list<array{0: string, 1: int}>  $candidates
 */
function pickNext(array $candidates, ?int $lastChapter): ?string
{
    $candidates = array_map(static fn (array $candidate): Candidate => new Candidate($candidate[0], $candidate[1]), $candidates);

    return (new NextQuestionPicker)->pick($candidates, $lastChapter)?->questionId;
}

it('LOGIC-T09〜T14 直前と同じ章をできる範囲で避けて、次の1問を選ぶ', function (array $candidates, ?int $lastChapter, ?string $expected): void {
    expect(pickNext($candidates, $lastChapter))->toBe($expected);
})->with([
    'LOGIC-T09 候補なし' => [[], 1, null],
    'LOGIC-T10 直前の回答なし' => [[['a', 1], ['b', 2]], null, 'a'],
    'LOGIC-T11 先頭が直前と同じ章' => [[['a', 1], ['b', 2]], 1, 'b'],
    'LOGIC-T12 同じ章が続く' => [[['a', 1], ['b', 1], ['c', 2]], 1, 'c'],
    'LOGIC-T13 すべて同じ章' => [[['a', 1], ['b', 1]], 1, 'a'],
    'LOGIC-T14 先頭が別の章' => [[['a', 2], ['b', 1]], 1, 'a'],
]);
