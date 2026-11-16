<?php

declare(strict_types=1);

// SCR-01 の本実装（docs/02_architecture.md 7章 手順7）までの仮のテスト
it('ホームが表示される', function (): void {
    $this->get('/')->assertOk()->assertSee('Laravel学習');
});
