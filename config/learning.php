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

    /*
    | 出典リンクの実在確認で読む、公式ドキュメントの原稿の場所（docs/commands/CMD-02_lessons-check-sources.md 3章）
    | この後ろに /{バージョン}/{ページ}.md を付けて取得する
    */
    'docs_source_base' => 'https://raw.githubusercontent.com/laravel/docs',

];
