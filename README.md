# Laravel学習アプリ

Laravelの概念・設計判断・ベストプラクティスを、想起練習と分散学習で身につけるための自分専用の教材アプリ。ローカル環境でのみ使う。

- 計画・設計: [docs/](docs/)（[プロジェクト計画書](docs/00_project-plan.md)から読む）
- 教材: `content/`（[教材フォーマット仕様書](docs/04_content-format.md)）

## 必要なもの

- PHP 8.4 以上（pdo_sqlite）
- Composer
- sqlite3 コマンド（バックアップ用）

Node.js・Dockerは不要。

## セットアップ

```bash
composer setup   # 依存関係のインストール、.env の作成、学習用DB（database/learning.sqlite）の作成とマイグレーション
php artisan serve
```

ブラウザで http://127.0.0.1:8000 を開く。

## DBファイル

学習記録を消さないため、用途ごとにDBを分けている（[方式設計書 4章](docs/02_architecture.md)）。

| 用途 | 起動 | 環境ファイル | DB |
| --- | --- | --- | --- |
| 学習 | `php artisan serve` | `.env` | `database/learning.sqlite` |
| 開発中の動作確認 | `APP_ENV=dev php artisan serve --port=8001` | `.env.dev` | `.env.dev` の `DB_DATABASE` |
| 自動テスト | `composer test` | `phpunit.xml` | メモリ上 |

- 学習用DBでは `migrate:fresh`・`migrate:refresh`・`migrate:reset`・`migrate:rollback`・`db:wipe` が実行できない（`LEARNING_DB_PROTECTED=true`）
- `php artisan config:cache` は使わない（テストが学習用DBに向く。テストには安全装置があるが、そもそも使わない）

開発用DBの準備:

```bash
cp .env.dev.example .env.dev   # DB_DATABASE を絶対パスに書き換える
php artisan key:generate --env=dev
touch database/dev.sqlite
php artisan migrate --env=dev
```

## バックアップ

週1回と、`migrate` の実行前・教材の取り込み前に行う。バックアップ先はリポジトリの外にする。

```bash
sqlite3 database/learning.sqlite ".backup '$HOME/Library/Mobile Documents/com~apple~CloudDocs/laravel-learning-backup/learning-$(date +%Y%m%d).sqlite'"
```

（`cp` は書き込み中にコピーすると壊れる可能性があるため、`.backup` を使う）

## 品質チェック

```bash
composer lint      # Pint（整形チェック）。composer format で整形
composer analyse   # Larastan（level max）
composer test      # Pest
```
