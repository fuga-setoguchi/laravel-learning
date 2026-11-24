# Laravel学習アプリ

Laravelの概念・設計判断・ベストプラクティスを、想起練習と分散学習で身につけるための自分専用の教材アプリ。ローカル環境でのみ使う。

- 計画・設計: [docs/](docs/)（[プロジェクト計画書](docs/00_project-plan.md)から読む）
- 教材: `content/`（[教材フォーマット仕様書](docs/04_content-format.md)）

> [!WARNING]
> `content/` の教材は、Claude Code（AI）が生成した個人の学習用のものです。誤りや古い情報を含む可能性があり、正確さは保証しません。正しい情報は、各レッスンの出典に書いた [Laravel 公式ドキュメント](https://laravel.com/docs) で確かめてください。

## 必要なもの

- PHP 8.4 以上（pdo_pgsql）
- Composer
- Docker（DBの PostgreSQL のみ。PHPはローカルで動かす）

Node.jsは不要。

## セットアップ

```bash
composer setup   # 依存関係、.env、DBのボリューム作成、PostgreSQLの起動、マイグレーション
make serve
```

ブラウザで http://127.0.0.1:8000 を開く。

## 毎日の起動

```bash
make start   # DBを起動して、学習用のサーバーを起動する
```

## よく使うコマンド

`make`（`make help`）で一覧を表示する。

| コマンド | すること |
| --- | --- |
| `make start` | DBを起動して、学習用のサーバーを起動する（`make up` → `make serve`） |
| `make up` | DB（PostgreSQL）を起動し、使えるようになるまで待つ |
| `make serve` | 学習用のサーバーを起動する |
| `make serve-dev` | 開発用DBで動作確認のサーバーを起動する |
| `make backup` | 学習用DBをバックアップする |
| `make import` | バックアップしてから、教材を学習用DBに取り込む |
| `make import-check` | 教材をテスト用DBで検証する（学習用DBは変更しない） |
| `make check` | 整形チェック・静的解析・テストをすべて実行する |

学習記録を消すコマンドは、Makefile に入れない。

## データベース

学習記録を消さないため、1つの PostgreSQL の中で用途ごとにデータベースを分けている（[方式設計書 4章](docs/02_architecture.md)）。

| 用途 | 起動 | 環境ファイル | データベース |
| --- | --- | --- | --- |
| 学習 | `make serve` | `.env` | `learning` |
| 開発中の動作確認 | `make serve-dev` | `.env.dev` | `learning_dev` |
| 自動テスト | `make check`（テストだけなら `composer test`） | `phpunit.xml` | `testing` |

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

週1回と、`migrate` の実行前に行う。`make import` は、取り込みの前に自動でバックアップする。バックアップ先はリポジトリの外にする。

```bash
make backup   # iCloud Drive の laravel-learning-backup に learning-YYYYMMDD.dump を保存する
make backup BACKUP_DIR=<保存先>   # 保存先を変える
```

復元:

```bash
docker compose exec -T db pg_restore -U app -d learning --clean --if-exists < <バックアップファイル>
```

## 品質チェック

テストの前に `make up` でDBを起動しておく。

```bash
make check   # 次の3つをまとめて実行する
composer lint      # Pint（整形チェック）。composer format で整形
composer analyse   # Larastan（level max）
composer test      # Pest
```

## ライセンス

[MIT License](LICENSE)
