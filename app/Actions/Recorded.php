<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ReviewLog;

/** 回答を記録した */
final readonly class Recorded
{
    public function __construct(public ReviewLog $log) {}
}
