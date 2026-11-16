<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** 出題の種類（docs/03_database.md review_logs.mode） */
enum ReviewMode: string
{
    use HasValues;

    /** 期限が来たことによる出題 */
    case Scheduled = 'scheduled';

    /** その日に不正解だった問題の再出題（段階に反映しない） */
    case Practice = 'practice';
}
