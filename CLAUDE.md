# CLAUDE.md

Laravel学習アプリ（ローカル専用・1人利用）。Laravel 13 / PHP 8.4 / Blade / PostgreSQL 18（Docker、DBのみ）/ Pest / Larastan。

## 必ず守ること

- 作業の前に [docs/00_project-plan.md](docs/00_project-plan.md) と、関係する設計書（`docs/`）を読む
- 設計書にない実装をしない。実装中に設計を変える場合は、先に設計書を更新する
- 設計書のID（SCR-01、CMD-01、FR-01、テストID など）をコード・テストのコメントや `it()` の説明文で引き継ぐ
- 全PHPファイルに `declare(strict_types=1)` と型宣言。Larastan は level max で、`@phpstan-ignore` やベースラインで黙らせない
- 作業の完了前に `composer lint`、`composer analyse`、`composer test` をすべて通す

## 学習用DBを守る

- PostgreSQL のデータベース `learning` は学習記録そのもの。消す・作り直す操作をしない
- `migrate:fresh` などは、開発用DB（`learning_dev`、`--env=dev`）かテスト（`testing`）でのみ使う
- `docker compose down -v`、`docker volume rm laravel-learning-pgdata`、`docker volume prune -a` を実行しない
- `php artisan config:cache` を実行しない
- `.env` / `.env.dev` は読まない・書き換えない（変更が必要なら利用者に依頼する）

## 構成

- 処理の置き場所は [docs/02_architecture.md](docs/02_architecture.md) 3.1 に従う（Controller は薄く、ユースケースは `app/Actions`、復習の計算は `app/Review` の純粋なクラス）
- 画面は Blade のみ。JavaScript はコードのハイライト以外に使わない。状態を変える操作は POST → リダイレクト（PRG）
- テストの方針は [docs/tests/README.md](docs/tests/README.md)
