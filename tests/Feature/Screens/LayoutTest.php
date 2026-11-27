<?php

declare(strict_types=1);

use App\Models\Lesson;
use App\View\Components\Icon;
use App\View\Components\Sidebar;
use Carbon\CarbonImmutable;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Route;

// docs/tests/LAYOUT_test.md

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-12-01 09:00', 'Asia/Tokyo'));
});

/**
 * 画面のHTMLから、メニュー項目（メニュー名・リンク先・選択中か・アイコンがあるか）を取り出す
 *
 * @return list<array{label: string, href: string, current: bool, icon: bool}>
 */
function menuItems(string $html): array
{
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
    $items = [];

    foreach ($document->querySelectorAll('nav[aria-label="メインメニュー"] a') as $link) {
        $items[] = [
            'label' => trim((string) $link->querySelector('span')?->textContent),
            'href' => (string) $link->getAttribute('href'),
            'current' => $link->getAttribute('aria-current') === 'page',
            'icon' => $link->querySelector('svg[aria-hidden="true"]') !== null,
        ];
    }

    return $items;
}

it('LAYOUT-T01 メニューに、ホーム・カリキュラム・復習がこの順でリンクとして表示される', function (): void {
    $items = menuItems($this->get(route('home'))->assertOk()->getContent() ?: '');

    expect(array_column($items, 'label'))->toBe(['ホーム', 'カリキュラム', '復習'])
        ->and(array_column($items, 'href'))->toBe([route('home'), route('curriculum'), route('review.show')]);
});

it('LAYOUT-T02 今いる画面の項目だけが選択中になる', function (string $page, string $current): void {
    $url = $page === 'lesson' ? route('lessons.show', Lesson::factory()->create()) : route($page);

    $items = menuItems($this->get($url)->assertOk()->getContent() ?: '');
    $currentLabels = array_column(array_filter($items, fn (array $item): bool => $item['current']), 'label');

    expect(array_values($currentLabels))->toBe([$current]);
})->with([
    'ホーム' => ['home', 'ホーム'],
    'カリキュラム' => ['curriculum', 'カリキュラム'],
    'レッスン' => ['lesson', 'カリキュラム'],
    '復習' => ['review.show', '復習'],
]);

it('LAYOUT-T03 メニュー項目ごとに、読み上げないアイコンが表示される', function (): void {
    $items = menuItems($this->get(route('home'))->getContent() ?: '');

    expect($items)->not->toBeEmpty()
        ->and(array_column($items, 'icon'))->each->toBeTrue();
});

it('LAYOUT-T04 一覧にないアイコン名は例外にする', function (): void {
    new Icon('missing');
})->throws(InvalidArgumentException::class);

it('LAYOUT-T05 すべてのメニュー項目のルート名が存在し、アイコンが一覧にある', function (): void {
    foreach ((new Sidebar)->items() as $item) {
        expect(Route::has($item->route))->toBeTrue()
            ->and(Icon::PATHS)->toHaveKey($item->icon);
    }
});
