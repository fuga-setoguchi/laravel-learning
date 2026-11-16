<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

/**
 * テスト実行中は Laravel が CSRF の検証を省くため、検証を有効にしたミドルウェアに差し替える。
 * CSRFトークンなしの送信が拒否されること（419）を確かめるテストで使う。
 */
final class EnablesCsrfProtection extends PreventRequestForgery
{
    protected function runningUnitTests()
    {
        return false;
    }
}
