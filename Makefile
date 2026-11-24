# よく使うコマンドをまとめる（README の「よく使うコマンド」を参照）
# 学習記録を消すコマンド（docker compose down -v、migrate:fresh、config:cache など）は書かない

BACKUP_DIR ?= $(HOME)/Library/Mobile Documents/com~apple~CloudDocs/laravel-learning-backup
BACKUP_FILE := $(BACKUP_DIR)/learning-$(shell date +%Y%m%d).dump

.DEFAULT_GOAL := help
.PHONY: help start up serve serve-dev backup import import-check check

help: ## コマンドの一覧を表示する
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "}; {printf "  make %-14s %s\n", $$1, $$2}'

start: up serve ## DBを起動して、学習用のサーバーを起動する（毎日の起動）

up: ## DB（PostgreSQL）を起動し、使えるようになるまで待つ
	docker compose up -d --wait

serve: ## 学習用のサーバーを起動する（http://127.0.0.1:8000）
	php artisan serve

serve-dev: ## 開発用DBで動作確認のサーバーを起動する（http://127.0.0.1:8001）
	APP_ENV=dev php artisan serve --port=8001

backup: ## 学習用DBをバックアップする（保存先は BACKUP_DIR）
	mkdir -p "$(BACKUP_DIR)"
	docker compose exec -T db pg_dump -U app -Fc learning > "$(BACKUP_FILE)"
	@echo "バックアップしました: $(BACKUP_FILE)"

import: backup ## バックアップしてから、教材を学習用DBに取り込む
	php artisan lessons:import

import-check: ## 教材をテスト用DBで検証する（学習用DBは変更しない）
	DB_DATABASE=testing php artisan migrate --force
	DB_DATABASE=testing php artisan lessons:import --dry-run

check: ## 整形チェック・静的解析・テストをすべて実行する
	composer lint
	composer analyse
	composer test
