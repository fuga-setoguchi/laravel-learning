<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * 教材のMarkdownをHTMLにする（docs/02_architecture.md 3.4）。
 * AIが生成した教材に含まれるHTMLやリンクを実行させないよう、安全な設定をここに集約する。
 */
final class Markdown
{
    public static function toHtml(string $markdown): HtmlString
    {
        return new HtmlString(Str::markdown($markdown, [
            // 教材中のHTMLタグは文字として表示する（XSS防止）
            'html_input' => 'escape',
            // javascript: などの危険なリンクを出力しない
            'allow_unsafe_links' => false,
        ]));
    }
}
