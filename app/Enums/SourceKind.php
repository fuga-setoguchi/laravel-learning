<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/** lesson_sources の種別（docs/03_database.md lesson_sources.kind） */
enum SourceKind: string
{
    use HasValues;

    /** 出典: レッスンの内容の根拠（対象バージョンの公式ドキュメント） */
    case Source = 'source';

    /** 補足資料: 経緯や背景（他のバージョンのドキュメント、公式ブログ） */
    case Reference = 'reference';
}
