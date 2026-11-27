<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use InvalidArgumentException;

/**
 * LAYOUT メニューのアイコン（docs/screens/LAYOUT_common.md 4章）。
 * 外部のファイルを読み込まないよう、自作の線画をインラインの SVG で表示する（24×24、線で描く）。
 */
final class Icon extends Component
{
    /** アイコン名 => SVG の中身 */
    public const array PATHS = [
        // 家
        'home' => '<path d="M3 11 12 3l9 8"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
        // 開いた本
        'book' => '<path d="M12 6c-2-1.5-5-2-8-2v14c3 0 6 .5 8 2 2-1.5 5-2 8-2V4c-3 0-6 .5-8 2z"/><path d="M12 6v14"/>',
        // 回る矢印
        'review' => '<path d="M20 12a8 8 0 1 1-2.34-5.66"/><path d="M20 4v5h-5"/>',
    ];

    public function __construct(public string $name)
    {
        if (! array_key_exists($name, self::PATHS)) {
            throw new InvalidArgumentException("アイコン「{$name}」はありません。");
        }
    }

    public function paths(): HtmlString
    {
        return new HtmlString(self::PATHS[$this->name]);
    }

    public function render(): View
    {
        return view('components.icon');
    }
}
