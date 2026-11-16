<?php

declare(strict_types=1);

namespace App\Actions;

/** 回答を記録しなかった（理由つき） */
final readonly class Rejected
{
    public function __construct(public RejectionReason $reason) {}
}
