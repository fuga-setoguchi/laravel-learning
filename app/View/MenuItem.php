<?php

declare(strict_types=1);

namespace App\View;

/** 左のメニューの1項目（docs/screens/LAYOUT_common.md 4章） */
final readonly class MenuItem
{
    /**
     * @param  string  $route  リンク先のルート名
     * @param  string  $icon  アイコン名（App\View\Components\Icon の一覧にある名前）
     * @param  list<string>  $activeRoutes  この項目を選択中にする画面のルート名（* を使える）
     */
    public function __construct(
        public string $label,
        public string $route,
        public string $icon,
        public array $activeRoutes,
    ) {}

    public function url(): string
    {
        return route($this->route);
    }

    public function isActive(): bool
    {
        return request()->routeIs(...$this->activeRoutes);
    }
}
