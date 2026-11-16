<?php

declare(strict_types=1);

// 復習（SCR-04）のメッセージ
return [

    // 回答を記録しなかった理由（docs/screens/SCR-04_review.md 3章）
    'rejected' => [
        'not_target' => 'この問題は復習の対象ではないため、記録しませんでした。',
        'already_answered' => 'この問題はすでに回答済みのため、記録しませんでした。',
        'practice_finished' => 'この問題の再出題はすでに終わっているため、記録しませんでした。',
        'stale_form' => 'この画面の後に回答が記録されているため、記録しませんでした。',
    ],

    // 種別と入力の組み合わせの検証（docs/05_review-logic.md 7.1）
    'invalid' => [
        'rating_required' => '評価を選んでください。',
        'choice_required' => '選択肢を選んでください。',
        'choice_unknown' => '選んだ選択肢がこの問題にありません。',
        'unexpected_input' => 'この問題の種別では送信できない項目が含まれています。',
    ],

];
