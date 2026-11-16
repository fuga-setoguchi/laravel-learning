<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** 回答の結果（docs/03_database.md review_logs.result） */
enum ReviewResult: string
{
    use HasValues;

    // 想起カードの自己評価
    case Remembered = 'remembered';
    case Vague = 'vague';
    case Forgot = 'forgot';

    // 選択式・AIコードレビュー問題の自動採点
    case Correct = 'correct';
    case Incorrect = 'incorrect';
}
