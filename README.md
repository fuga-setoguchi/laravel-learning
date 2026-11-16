# Laravel学習アプリ

Laravelの概念・設計判断・ベストプラクティスを、想起練習と分散学習で身につけるための自分専用の教材アプリ。ローカル環境でのみ使う。

- 計画・設計: [docs/](docs/)（[プロジェクト計画書](docs/00_project-plan.md)から読む）
- 教材: `content/`（[教材フォーマット仕様書](docs/04_content-format.md)）

## 必要なもの

- PHP 8.4 以上（pdo_pgsql）
- Composer
- Docker（DBの PostgreSQL のみ。PHPはローカルで動かす）

Node.jsは不要。

## セットアップ

```bash
composer setup   # 依存関係、.env、DBのボリューム作成、PostgreSQLの起動、マイグレーション
php artisan serve
```

ブラウザで http://127.0.0.1:8000 を開く。

## 毎日の起動

```bash
docker compose up -d
php artisan serve
```

## データベース

学習記録を消さないため、1つの PostgreSQL の中で用途ごとにデータベースを分けている（[方式設計書 4章](docs/02_architecture.md)）。

| 用途 | 起動 | 環境ファイル | データベース |
| --- | --- | --- | --- |
| 学習 | `php artisan serve` | `.env` | `learning` |
| 開発中の動作確認 | `APP_ENV=dev php artisan serve --port=8001` | `.env.dev` | `learning_dev` |
| 自動テスト | `composer test` | `phpunit.xml` | `testing` |

- データは外部ボリューム `laravel-learning-pgdata` に保存する。`docker compose down -v` では消えない
- 次のコマンドは実行しない（学習記録が消える）
  - `docker volume rm laravel-learning-pgdata`、`docker volume prune -a`
  - 学習用DBでの `migrate:fresh`・`migrate:refresh`・`migrate:reset`・`migrate:rollback`・`db:wipe`（`LEARNING_DB_PROTECTED=true` で禁止している）
- `php artisan config:cache` は使わない（テストが学習用DBに向く。テストには安全装置があるが、そもそも使わない）

開発用DBの準備:

```bash
cp .env.dev.example .env.dev
php artisan key:generate --env=dev
php artisan migrate --env=dev
```

## バックアップ

週1回と、`migrate` の実行前・教材の取り込み前に行う。バックアップ先はリポジトリの外にする。

```bash
docker compose exec -T db pg_dump -U app -Fc learning > "$HOME/Library/Mobile Documents/com~apple~CloudDocs/laravel-learning-backup/learning-$(date +%Y%m%d).dump"
```

復元:

```bash
docker compose exec -T db pg_restore -U app -d learning --clean --if-exists < <バックアップファイル>
```

## 品質チェック

テストの前に `docker compose up -d` でDBを起動しておく。

```bash
composer lint      # Pint（整形チェック）。composer format で整形
composer analyse   # Larastan（level max）
composer test      # Pest
```
