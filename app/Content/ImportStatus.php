<?php

declare(strict_types=1);

namespace App\Content;

/** 1行の取り込み結果 */
enum ImportStatus
{
    case Added;
    case Updated;
    case Unchanged;
    /** 引退していた行がファイルに戻った */
    case Restored;
}
