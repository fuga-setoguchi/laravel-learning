<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** 問題の種別（docs/03_database.md questions.type） */
enum QuestionType: string
{
    use HasValues;

    case Recall = 'recall';
    case Choice = 'choice';
    case CodeReview = 'code_review';
}
