<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 学習用DBで記録を消すコマンドを禁止する（docs/02_architecture.md 4章）
        DB::prohibitDestructiveCommands(config()->boolean('learning.db_protected'));

        // 日付は変更不可の CarbonImmutable で扱う（docs/02_architecture.md 3.5）
        Date::use(CarbonImmutable::class);

        // 遅延ロード・存在しない属性・黙って捨てられる代入を例外にする（docs/03_database.md 5章）
        Model::shouldBeStrict();
    }
}
