<?php

declare(strict_types=1);

// 画面のフォームの検証メッセージ。通常の操作では表示されない（不正な送信のとき）
return [
    'required' => ':attribute は必須です。',
    'string' => ':attribute は文字列で指定してください。',
    'integer' => ':attribute は整数で指定してください。',
    'min' => [
        'numeric' => ':attribute は :min 以上にしてください。',
    ],
    'in' => ':attribute の値が正しくありません。',
    'enum' => ':attribute の値が正しくありません。',
    'exists' => ':attribute が見つかりません。',

    'attributes' => [
        'question_id' => '問題',
        'mode' => '出題の種類',
        'last_log_id' => '画面の状態',
        'rating' => '評価',
        'choice_key' => '選択肢',
    ],
];
