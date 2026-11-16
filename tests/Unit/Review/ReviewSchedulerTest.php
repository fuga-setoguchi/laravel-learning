<?php

declare(strict_types=1);

use App\Enums\Grade;
use App\Review\ReviewScheduler;
use Carbon\CarbonImmutable;

// docs/tests/LOGIC_test.md 1章（今日 = 2026-12-01）

function today20261201(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-12-01', 'Asia/Tokyo');
}

it('LOGIC-T01 初期状態は段階1・翌日', function (): void {
    $state = (new ReviewScheduler)->initial(today20261201());

    expect($state->stage)->toBe(1)
        ->and($state->nextReviewOn->toDateString())->toBe('2026-12-02');
});

it('LOGIC-T02 pass で段階が1つ上がり、新しい段階の日数後になる', function (int $stage, int $newStage, string $nextReviewOn): void {
    $state = (new ReviewScheduler)->next($stage, Grade::Pass, today20261201());

    expect($state->stage)->toBe($newStage)
        ->and($state->nextReviewOn->toDateString())->toBe($nextReviewOn);
})->with([
    '段階1' => [1, 2, '2026-12-04'],
    '段階2' => [2, 3, '2026-12-08'],
    '段階3' => [3, 4, '2026-12-15'],
    '段階4' => [4, 5, '2026-12-31'],
    '段階5' => [5, 6, '2027-01-30'],
]);

it('LOGIC-T03 段階6で pass しても段階6のまま、60日後になる', function (): void {
    $state = (new ReviewScheduler)->next(6, Grade::Pass, today20261201());

    expect($state->stage)->toBe(6)
        ->and($state->nextReviewOn->toDateString())->toBe('2027-01-30');
});

it('LOGIC-T04 hold で段階はそのまま、その段階の日数後になる', function (int $stage, string $nextReviewOn): void {
    $state = (new ReviewScheduler)->next($stage, Grade::Hold, today20261201());

    expect($state->stage)->toBe($stage)
        ->and($state->nextReviewOn->toDateString())->toBe($nextReviewOn);
})->with([
    '段階1' => [1, '2026-12-02'],
    '段階2' => [2, '2026-12-04'],
    '段階3' => [3, '2026-12-08'],
    '段階4' => [4, '2026-12-15'],
    '段階5' => [5, '2026-12-31'],
    '段階6' => [6, '2027-01-30'],
]);

it('LOGIC-T05 fail で段階1に戻り、翌日になる', function (int $stage): void {
    $state = (new ReviewScheduler)->next($stage, Grade::Fail, today20261201());

    expect($state->stage)->toBe(1)
        ->and($state->nextReviewOn->toDateString())->toBe('2026-12-02');
})->with([1, 2, 3, 4, 5, 6]);

it('LOGIC-T06 段階が1〜6の範囲外なら例外になる', function (int $stage): void {
    expect(fn () => (new ReviewScheduler)->next($stage, Grade::Pass, today20261201()))
        ->toThrow(InvalidArgumentException::class);
})->with([0, 7]);

it('LOGIC-T07 月末・年末をまたいで計算できる', function (): void {
    $state = (new ReviewScheduler)->next(1, Grade::Pass, CarbonImmutable::parse('2026-12-31', 'Asia/Tokyo'));

    expect($state->nextReviewOn->toDateString())->toBe('2027-01-03');
});

it('LOGIC-T49 時刻つきの今日を渡しても、次回の復習日は0時になる', function (): void {
    $state = (new ReviewScheduler)->initial(CarbonImmutable::parse('2026-12-01 23:59:59', 'Asia/Tokyo'));

    expect($state->nextReviewOn->format('Y-m-d H:i:s'))->toBe('2026-12-02 00:00:00');
});
