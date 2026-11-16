<?php

declare(strict_types=1);

namespace App\Enums;

/** 回答の評価。段階の更新に使う（docs/05_review-logic.md 2章） */
enum Grade
{
    /** 思い出せた / 正解: 段階を1つ上げる */
    case Pass;

    /** あいまい: 段階はそのまま */
    case Hold;

    /** 思い出せなかった / 不正解: 段階1に戻す */
    case Fail;
}
