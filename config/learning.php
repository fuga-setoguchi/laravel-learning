<?php

declare(strict_types=1);

return [

    /*
    | 教材フォルダ（docs/04_content-format.md）
    */
    'content_path' => base_path('content'),

    /*
    | 学習用DBの保護（docs/02_architecture.md 4章）
    | true のとき migrate:fresh などの記録を消すコマンドを禁止する。
    | 設定漏れで記録が消えないよう、既定は true（開発用・テスト用で明示的に false にする）。
    */
    'db_protected' => (bool) env('LEARNING_DB_PROTECTED', true),

];
