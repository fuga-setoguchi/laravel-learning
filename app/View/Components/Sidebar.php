<?php

declare(strict_types=1);

namespace App\View\Components;

use App\View\MenuItem;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * LAYOUT 左のメニュー（docs/screens/LAYOUT_common.md）。
 * 機能を足すときは、items() の末尾に項目を1つ足す。
 */
final class Sidebar extends Component
{
    /** @return list<MenuItem> */
    public function items(): array
    {
        return [
            new MenuItem('ホーム', 'home', 'home', ['home']),
            new MenuItem('カリキュラム', 'curriculum', 'book', ['curriculum', 'lessons.*']),
            new MenuItem('復習', 'review.show', 'review', ['review.*']),
        ];
    }

    public function render(): View
    {
        return view('components.sidebar');
    }
}
