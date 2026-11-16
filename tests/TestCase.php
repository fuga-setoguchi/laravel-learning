<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * 安全装置: 設定のキャッシュなどで接続先がメモリ上のDBでない場合、
     * 学習用DBの記録を消さないようテストを中断する（docs/02_architecture.md 4章）。
     *
     * setUp() では RefreshDatabase が先にDBを初期化してしまうため、
     * アプリケーションの生成直後（トレイトの準備より前）に確認する。
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $config = $app->make('config');
        $connection = $config->get('database.default');
        $database = $config->get("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                'テストの接続先がメモリ上のSQLiteではありません（'.var_export($database, true).'）。'
                .'php artisan config:clear を実行してから、もう一度テストしてください。'
            );
        }

        return $app;
    }
}
