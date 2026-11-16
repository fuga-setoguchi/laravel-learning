<?php

declare(strict_types=1);

// 教材の取り込み（CMD-01）のメッセージ。項目のパスはメッセージの前に表示するため、:attribute は使わない
return [

    // 読み込み・解析
    'folder_missing' => '教材フォルダがありません。',
    'chapters_missing' => 'chapters.yaml がありません。',
    'unreadable' => 'ファイルを読み込めません。',
    'front_matter_missing' => 'front matter（先頭の --- で囲まれたYAML）がありません。',
    'front_matter_invalid' => 'front matter のYAMLを解析できません（:reason）。',
    'front_matter_not_mapping' => 'front matter は「項目名: 値」の形式で書いてください。',
    'chapters_yaml_invalid' => 'chapters.yaml のYAMLを解析できません（:reason）。',

    // Validator の規則ごとのメッセージ
    'rules' => [
        'required' => '必須です。',
        'string' => '文字列で書いてください。',
        'integer' => '整数で書いてください。',
        'array' => '項目の集まりとして書いてください。',
        'list' => '「- 」で始まる配列として書いてください。',
        'min' => ':min 件以上必要です。',
        'max' => ':max 文字以内にしてください。',
        'between' => ':min〜:max 件にしてください。',
        'distinct' => '値（:input）が重複しています。',
        'prohibited' => 'この項目は書かないでください。',
        'in' => '使える値ではありません（:input）。',
    ],

    // 項目ごとのメッセージ（規則のメッセージより優先される）
    'attributes' => [
        'id_regex' => 'ch + 2桁の章番号 + -l + 2桁の順番（例: ch03-l02）で書いてください。',
        'laravel_version_regex' => '「13.x」の形式で書いてください。',
        'answer_in' => 'choices に存在しない key（:input）です。',
        'key_in' => 'a〜f のいずれかにしてください（:input）。',
        'type_in' => 'choice または code_review にしてください（:input）。',
        'code_language_in' => 'php / blade / bash / json / yaml / sql のいずれかにしてください（:input）。',
        'recall_card_id_regex' => ':lesson-c + 番号（例: :lesson-c1）で書いてください（:input）。',
        'question_id_regex' => ':lesson-q + 番号（例: :lesson-q1）で書いてください（:input）。',
        'number_between' => '1〜99 の整数にしてください。',
    ],

    // ファイルの位置・ファイルをまたぐ検証
    'id_mismatch' => 'ファイルの位置（:expected）と一致しません（:actual）。',
    'chapter_mismatch' => 'id の章番号（:expected）と一致しません（:actual）。',
    'order_mismatch' => 'id の順番（:expected）と一致しません（:actual）。',
    'chapter_not_defined' => 'chapters.yaml に章 :chapter がありません。',

    // 出典URL
    'docs_url' => [
        'prefix' => 'https://laravel.com/docs/ で始まる公式ドキュメントのURLにしてください。',
        'version' => 'URLのバージョン（:actual）が laravel_version（:expected）と一致しません。',
        'page' => 'ページ名がありません（バージョンのページだけを指しています）。',
    ],

    // 警告
    'warnings' => [
        'recall_cards_count' => ':file: recall_cards が :count 件です（推奨 3〜5 件）',
        'questions_count' => ':file: questions が :count 件です（推奨 1〜2 件）',
        'no_code_review' => ':file: AIコードレビュー問題（code_review）がありません',
        'mixed_versions' => 'laravel_version がレッスンによって異なります（:versions）',
    ],

];
