# 自動テスト設計 共通方針

作成日: 2026-11-16
ステータス: 確定（2026-11-16）

## 1. テストの種類

| 種類 | ツール | 対象 | 配置 |
| --- | --- | --- | --- |
| 単体テスト | Pest | ReviewScheduler、NextQuestionPicker、ChoiceShuffler、Enum の変換、LessonFileParser | `tests/Unit/` |
| 機能テスト | Pest | CMD-01（コマンド〜DB）、CMD-02（コマンド〜HTTP。HTTP クライアントは偽物にする）、SCR-01〜04（HTTPリクエスト〜DB・HTML）、CompleteLesson、RecordReviewAnswer、ReviewQueue | `tests/Feature/` |
| 環境テスト | Pest | ENV-T01〜03（5章）。`migrate:fresh` を実際に実行するため `RefreshDatabase` を使わない | `tests/Environment/` |

ブラウザテスト（JavaScriptの実行）は行わない。画面はJavaScriptに依存しないため、HTTPレスポンスのHTMLで検証できる。

## 2. 方針

- DBはDockerのPostgreSQLのテスト用データベース `testing`（`phpunit.xml` で `force` 指定）を使い、`RefreshDatabase` で初期化する（最初に1回 `migrate:fresh`、各テストはトランザクションで巻き戻す）。テストの前に `docker compose up -d` が必要
- 時刻は `$this->travelTo(CarbonImmutable::parse('2026-12-01 09:00', 'Asia/Tokyo'))` などで固定する。日付の境界（0時）をまたぐテストを含める
- テストデータはモデルファクトリで作る。文章は `fake()->sentence()` などの軽いものを使う（ja_JP の `realText()` は大きな原文を読み込み、テストのメモリを使い切るため）。復習のテストデータは `tests/Support/ReviewFixture.php` でまとめて作る。`ReviewState` / `ReviewLog` のファクトリには「期限が来た」「今日不正解」などの状態（`state`）を用意する
- 教材取り込みのテストは `tests/Fixtures/content/` の教材フォルダを `--path` で指定する。正常な教材一式（`valid/`）と、エラーごとの最小のファイルを用意する。エラーのファイルは、テスト内で正常な教材を一時フォルダにコピーして一部を書き換えて作る（`tests/Support/ContentFixture.php`。一時フォルダはテストの終了時に消す）
- 画面のテストは、ステータス・リダイレクト先・表示される文言・DBの状態で検証する。HTMLの構造の細部（クラス名など）は検証しない
- DBの制約違反を確かめるテストでは、失敗するはずの処理を `DB::transaction()`（セーブポイント）の中で実行する。PostgreSQL はトランザクション内でエラーが起きると以降のクエリをすべて拒否するため、`RefreshDatabase` のトランザクションが使えなくなる
- N+1がないことは、`DB::enableQueryLog()` でクエリ数がデータ件数に比例しないことを確認する（件数を変えた2回で同じクエリ数）
- `Model::shouldBeStrict()` をテストで有効にし、遅延ロードを例外にする

## 3. テストIDの付け方

`{設計書ID}-T{連番2桁}`（例: CMD-01-T01）。復習ロジックは `LOGIC-T{連番2桁}`、DBは `DB-T{連番2桁}`。テストコードの `it()` の説明文の先頭にIDを書き、設計書と対応付ける。

## 4. 一覧

| 設計書 | 対象 |
| --- | --- |
| [DB_test.md](DB_test.md) | DB（マイグレーション・制約・モデル） |
| [LOGIC_test.md](LOGIC_test.md) | 復習ロジック（ReviewScheduler、NextQuestionPicker、ChoiceShuffler、ReviewQueue、CompleteLesson、RecordReviewAnswer） |
| [CMD-01_test.md](CMD-01_test.md) | 教材の取り込み |
| [CMD-02_test.md](CMD-02_test.md) | 出典リンクの実在確認 |
| [SCR-01_test.md](SCR-01_test.md) | ホーム |
| [SCR-02_test.md](SCR-02_test.md) | カリキュラム |
| [SCR-03_test.md](SCR-03_test.md) | レッスン |
| [SCR-04_test.md](SCR-04_test.md) | 復習 |

## 5. 環境

| テスト | 内容 |
| --- | --- |
| ENV-T01 | テスト内で `DB::prohibitDestructiveCommands(true)` を直接呼んだ後、`migrate:fresh` が実行されず失敗する（設定は起動時に読まれるため、テストでは設定値ではなく直接呼ぶ）。あわせて `AppServiceProvider` が `config('learning.db_protected')` の値を渡していることを確認する |
| ENV-T02 | `LEARNING_DB_PROTECTED=false` のとき、`migrate:fresh` が実行できる |
| ENV-T03 | テスト実行時の接続先が `pgsql` の `testing` である（安全装置は `tests/TestCase.php` の `createApplication()`） |
