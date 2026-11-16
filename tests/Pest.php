<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// 環境のテスト（docs/tests/README.md 5章）は migrate:fresh などを実際に実行するため、
// RefreshDatabase のトランザクションを使わない
pest()->extend(TestCase::class)
    ->in('Environment');
