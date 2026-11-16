<?php

declare(strict_types=1);

use App\Enums\Grade;
use App\Enums\ReviewResult;

// docs/tests/LOGIC_test.md 2章

it('LOGIC-T08 回答の結果を評価に変換する', function (ReviewResult $result, Grade $grade): void {
    expect($result->grade())->toBe($grade);
})->with([
    '思い出せた' => [ReviewResult::Remembered, Grade::Pass],
    '正解' => [ReviewResult::Correct, Grade::Pass],
    'あいまい' => [ReviewResult::Vague, Grade::Hold],
    '思い出せなかった' => [ReviewResult::Forgot, Grade::Fail],
    '不正解' => [ReviewResult::Incorrect, Grade::Fail],
]);
