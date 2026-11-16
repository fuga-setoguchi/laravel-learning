<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// docs/tests/README.md 5章

afterEach(function (): void {
    DB::prohibitDestructiveCommands(false);
});

it('ENV-T01 学習用DBの保護が有効なとき、記録を消すコマンドを実行できない', function (string $command): void {
    DB::prohibitDestructiveCommands(true);

    $exitCode = Artisan::call($command, ['--force' => true]);

    expect($exitCode)->not->toBe(0);
})->with(['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback', 'db:wipe']);

it('ENV-T01 AppServiceProvider が保護の設定値を渡している', function (): void {
    config(['learning.db_protected' => true]);
    (new AppServiceProvider(app()))->boot();

    expect(Artisan::call('migrate:fresh', ['--force' => true]))->not->toBe(0);
});

it('ENV-T02 学習用DBの保護が無効なとき、migrate:fresh を実行できる', function (): void {
    DB::prohibitDestructiveCommands(false);

    expect(Artisan::call('migrate:fresh', ['--force' => true]))->toBe(0);
});

it('ENV-T03 テストの接続先がメモリ上のSQLiteである', function (): void {
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');
});

it('アプリのタイムゾーンが Asia/Tokyo で、日付が CarbonImmutable である', function (): void {
    expect(config('app.timezone'))->toBe('Asia/Tokyo')
        ->and(now())->toBeInstanceOf(CarbonImmutable::class);
});
