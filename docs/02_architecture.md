# 方式設計書

作成日: 2026-11-16
ステータス: 確定（2026-11-16）
参照: [プロジェクト計画書](00_project-plan.md) / [要件定義書](01_requirements.md)

## 1. システム構成

```
┌──────────── ローカルPC ─────────────────────────────────────┐
│                                                            │
│  ブラウザ ──HTTP──▶ php artisan serve（Laravel / Blade）      │
│     │                    │                                  │
│                          │ Eloquent                         │
│                          ▼                                  │
│                      database/learning.sqlite（学習用DB）     │
│                                                            │
│  content/*.md ──php artisan lessons:import──▶ 学習用DB        │
└────────────────────────────────────────────────────────────┘
```

- 1つのLaravelアプリのみで構成する。キュー・スケジューラ・Node.js・Dockerは使わない
- リポジトリのルートがLaravelのプロジェクトルート。設計書は `docs/`、教材は `content/` に置く

## 2. 技術スタック

環境構築時（2026-11-16）に確定したバージョン。

| 領域 | 採用技術 | バージョン | 用途 |
| --- | --- | --- | --- |
| 言語 | PHP | 8.4（ローカルの版に合わせる。CIも8.4） | |
| フレームワーク | Laravel | 13.x（13.42） | |
| DB | SQLite | PHP同梱 | 学習用・開発用・テスト用でファイルを分ける（4章） |
| 画面 | Blade + Bladeコンポーネント | Laravel同梱 | |
| CSS | Pico CSS（classless版、`public/vendor/` に同梱） | 2.x | Node.jsなしで最低限の見た目を整える |
| Markdown | league/commonmark（`Str::markdown`） | Laravel同梱 | 本文・問題文の表示 |
| front matter | league/commonmark の FrontMatterExtension + symfony/yaml（`composer require symfony/yaml` が必要） | | 教材ファイルの解析 |
| ハイライト | highlight.js（`public/vendor/` に同梱） | 11.x | 本文中のコード表示 |
| 静的解析 | Larastan | 3.x（level max） | |
| 整形 | Laravel Pint | `laravel` プリセット + `declare_strict_types` | |
| テスト | Pest | 5.x | |
| CI | GitHub Actions | | |

Pico CSS と highlight.js は、バージョンを固定したファイルを公式の配布物からダウンロードして `public/vendor/` に置き、gitで管理する（Node.js不要・オフラインでも動く・CDNの改ざんの影響を受けない）。

## 3. 方式設計

### 3.1 ディレクトリ構成

Laravel標準の構成に、以下を追加する。各クラスの置き場所の判断理由も学習対象とするため、理由を併記する。

```
app/
├── Console/Commands/ImportLessonsCommand.php   # CMD-01。入出力のみ。処理は Content に委譲
├── Content/                                    # 教材の取り込み（ファイル → DB）
│   ├── LessonFileParser.php                    # Markdown → front matter の配列 + 本文
│   ├── LessonFileValidator.php                 # 配列の検証（Laravel の Validator を使う）
│   ├── ContentImporter.php                     # 検証済みの教材をトランザクションでDBへ反映
│   ├── ImportResult.php                        # 追加・更新・変更なし・引退・復活の件数と警告（readonly）
│   └── Data/ParsedLesson.php ほか              # 検証済みの教材を表す readonly クラス
├── Review/                                     # 復習のドメインロジック
│   ├── ReviewScheduler.php                     # 段階と次回の復習日の計算（純粋なクラス、DB不使用）
│   ├── ScheduledState.php / Candidate.php      # 計算結果・出題候補（readonly）
│   ├── NextQuestionPicker.php                  # 候補から次の1問を選ぶ（純粋なクラス、DB不使用）
│   ├── ReviewQueue.php                         # DBから候補を集めて NextQuestionPicker に渡す
│   └── ChoiceShuffler.php                      # 選択肢の決定的シャッフル
├── Actions/
│   ├── CompleteLesson.php / CompleteLessonResult.php      # レッスン完了 + 復習への登録
│   └── RecordReviewAnswer.php / Recorded.php / Rejected.php # 回答の検証・記録・段階の更新
├── Casts/DateOnly.php                          # 日付を Y-m-d で保存（03_database 1章）
├── ValueObjects/PreQuestion.php                # lessons.pre_question のキャスト先
├── Enums/
│   ├── QuestionType.php                        # recall / choice / code_review
│   ├── ReviewResult.php                        # remembered / vague / forgot / correct / incorrect
│   ├── Grade.php                               # pass / hold / fail（ReviewResult から変換）
│   └── ReviewMode.php                          # scheduled / practice
├── Http/
│   ├── Controllers/                            # HomeController, CurriculumController,
│   │                                           # LessonController, ReviewController
│   └── Requests/SubmitReviewAnswerRequest.php
├── Models/                                     # Chapter, Lesson, LessonSource, Question,
│                                               # QuestionChoice, LessonProgress, ReviewState, ReviewLog
└── Support/Markdown.php                        # Str::markdown の安全な設定をまとめる
resources/views/
├── layouts/app.blade.php
├── components/                                 # markdown, source-list, choice-form など
├── home.blade.php / curriculum.blade.php
├── lessons/{pre-question,read,recall,completed}.blade.php
└── review/{question,result,done}.blade.php
content/
├── chapters.yaml
└── ch01/l01.md ...
```

責務の分け方：

| 層 | 責務 | 置かないもの |
| --- | --- | --- |
| Controller | リクエストを受け、Action / ReviewQueue を呼び、ビューを返すかリダイレクトする | 段階の計算、出題順の決定、DBの更新処理 |
| FormRequest | 入力の形式の検証（値が存在するか、列挙値か） | 「今その問題に回答してよいか」の判定（DBの状態に依存するため Action で行う） |
| Action | 1つのユースケース（完了、回答）をトランザクションで実行する | HTTPに依存する処理（リダイレクト、フラッシュメッセージ） |
| Review/ の純粋なクラス | 日付と段階の計算、候補からの選択 | DBアクセス、`now()` の呼び出し（今日の日付は引数で受け取る） |
| Model | リレーション、キャスト、スコープ（`active()` など） | ユースケースの手順 |

### 3.2 ルーティング

| メソッド | パス | 名前 | 画面・処理 |
| --- | --- | --- | --- |
| GET | `/` | `home` | SCR-01 |
| GET | `/curriculum` | `curriculum` | SCR-02 |
| GET | `/lessons/{lesson}` | `lessons.show` | SCR-03 段階1 事前の問い |
| GET | `/lessons/{lesson}/read` | `lessons.read` | SCR-03 段階2 本文（`?answer=` で事前の問いの正誤を表示） |
| GET | `/lessons/{lesson}/recall` | `lessons.recall` | SCR-03 段階3 その場で思い出す |
| POST | `/lessons/{lesson}/complete` | `lessons.complete` | 完了処理 → `lessons.completed` へリダイレクト |
| GET | `/lessons/{lesson}/completed` | `lessons.completed` | SCR-03 段階4 完了 |
| GET | `/review` | `review.show` | SCR-04 次の問題 / 完了 |
| POST | `/review/answers` | `review.answers.store` | 回答の記録 → 選択式は `review.answers.show`、想起カードは `review.show` へ |
| GET | `/review/answers/{reviewLog}` | `review.answers.show` | SCR-04 正誤と解説 |

- `{lesson}` は文字列の教材id（`ch03-l02`）で暗黙の結合を行う。引退したレッスンは404とする（ルートモデル結合で `active()` スコープを適用する）
- 想定外のパス・存在しないidは404。Laravel標準のエラー画面を使う

### 3.3 画面の作り方

- 1操作1画面遷移。状態を変える送信はPOSTで行い、処理後はリダイレクトする（PRG）
- 状態を変えない送信（事前の問いの回答）はGETフォームとする
- 答えの開閉は `<details>` / `<summary>` で行う
- フラッシュメッセージ（`session('status')`）で、記録しなかった送信の理由などを1回だけ表示する
- CSRF対策はLaravel標準（`@csrf`）を使う

### 3.4 Markdownの表示

`App\Support\Markdown::toHtml(string): HtmlString` に集約し、以下の設定で `Str::markdown` を呼ぶ。

| 設定 | 値 | 理由 |
| --- | --- | --- |
| `html_input` | `escape` | 教材中のHTMLをそのまま出力しない（XSS防止） |
| `allow_unsafe_links` | `false` | `javascript:` などのリンクを無効化 |

本文中のフェンス付きコードブロック（```` ```php ````）は `<pre><code class="language-php">` で出力され、highlight.jsが色付けする。ハイライトが読み込めない場合もコードはそのまま読める。

### 3.5 日付と時刻

- `config/app.php` の `timezone` を `Asia/Tokyo` にする。DBの日時もAsia/Tokyoで保存する
- 日付・時刻は `CarbonImmutable` を使う（`AppServiceProvider` で `Date::use(CarbonImmutable::class)`）
- 「今日」は `today()` で取得し、Controller / Action から純粋なクラスへ引数で渡す
- テストでは `$this->travelTo()` / `freezeTime()` で時刻を固定する

### 3.6 エラー処理

| 場面 | 扱い |
| --- | --- |
| 存在しない・引退したレッスン / 回答履歴 | 404 |
| フォームの形式不正（列挙値外など） | FormRequest で 422 相当 → 直前の画面へ戻りエラー表示（通常の操作では起こらない） |
| 記録しない送信（期限前・二重送信・対象外の練習） | 記録せず `review.show` へリダイレクトし、フラッシュメッセージで理由を表示する。エラーではない |
| 取り込みの検証エラー | コマンドがファイル名・項目・理由を一覧表示し、終了コード1で終わる |

## 4. 環境とデータベースファイル

学習記録を消さないため、用途ごとにDBファイルを分ける。

| 用途 | 起動方法 | 環境ファイル | DB |
| --- | --- | --- | --- |
| 学習（毎日使う） | `php artisan serve` | `.env`（`APP_ENV=local`、`DB_DATABASE` は書かない） | `database/learning.sqlite`（`config/database.php` の既定値。`database_path()` による絶対パス） |
| 開発中の動作確認 | `APP_ENV=dev php artisan serve --port=8001`。Artisanコマンドは `--env=dev` を付ける | `.env.dev` | `database/dev.sqlite` |
| 自動テスト | `composer test` | `phpunit.xml` | `:memory:` |

- `DB_DATABASE` を書く場合は絶対パスにする（`php artisan serve` の子プロセスは `public/` を作業ディレクトリにするため、相対パスは別の場所を指す。`ServeCommand` のソースで確認済み）。学習用は既定値（絶対パス）を使い、`.env` には書かない
- アプリ固有の設定は `config/learning.php` にまとめる（`content_path`、`db_protected`）
- `.env` に `LEARNING_DB_PROTECTED=true` を設定し、`AppServiceProvider` で `DB::prohibitDestructiveCommands(config('learning.db_protected'))` を呼ぶ。これにより `migrate:fresh`・`migrate:refresh`・`migrate:reset`・`migrate:rollback`・`db:wipe` が学習用DBでは実行できない（Laravel 13.42 のソースで確認済み。テスト ENV-T01 で担保）。`config/learning.php` の既定値は `true` とし、設定漏れでも保護される側に倒す。`migrate`（追加のみ）は実行できる
- `.env.dev` と `phpunit.xml` では `LEARNING_DB_PROTECTED=false`
- `php artisan config:cache` は使わない。設定がキャッシュされていると、テスト実行時に `phpunit.xml` の `DB_DATABASE=:memory:` が効かず、学習用DBに対してテストが走り記録が消える。`tests/TestCase.php` の `createApplication()` で、接続先のDBが `:memory:` でなければテストを中断する安全装置を入れる（`setUp()` では `RefreshDatabase` が先に動くため、アプリ生成の直後に確認する）
- `APP_ENV=dev php artisan serve` では、`serve` が子プロセスに `APP_ENV` を渡し（`ServeCommand::$passthroughVariables`）、子プロセスが `.env.dev` を読み込む（ソースで確認済み。実際の起動確認は `.env.dev` 作成後に行う）
- `*.sqlite` はgitで管理しない

### 4.1 バックアップ

- 手順: `sqlite3 database/learning.sqlite ".backup '<バックアップ先>/learning-YYYYMMDD.sqlite'"`（`cp` は書き込み中だと壊れる可能性があるため `.backup` を使う）
- 頻度: 週1回と、`migrate` の実行前・教材の取り込み前
- バックアップ先: リポジトリの外（iCloud Drive など）
- 手順はREADMEに記載する

## 5. CI（GitHub Actions）

URL短縮サービスの `ci.yml` を元に、MySQL・フロントエンド・OpenAPIのジョブを除いた1ジョブとする。

| ステップ | コマンド |
| --- | --- |
| 依存関係のインストール | `composer install` |
| 依存関係の脆弱性確認 | `composer audit` |
| 整形チェック | `composer lint`（`pint --test`） |
| 静的解析 | `composer analyse`（`phpstan analyse`） |
| テスト | `composer test`（`pest`） |
| 教材の検証（CMD-01 の実装時に追加） | `touch database/ci.sqlite && php artisan migrate --force && php artisan lessons:import --dry-run`（`DB_DATABASE=database/ci.sqlite`。`:memory:` はコマンドごとに別のDBになるため、ファイルを使う） |

- 権限は `contents: read` のみ。actions はコミットハッシュで固定する
- Larastanの対象は `app`、`config`、`database`、`routes`、`bootstrap/app.php`

## 6. セキュリティ設計

ローカル専用・1人利用のため、外部公開を前提とした対策（認証、レート制限、セキュリティヘッダー）は行わない。以下のみ行う。

| 対象 | 対策 |
| --- | --- |
| 教材の本文・問題文 | `html_input: escape`、`allow_unsafe_links: false`（3.4） |
| 出典URL | `https://laravel.com/docs/{version}/` で始まることを取り込み時に検証。リンクは `rel="noopener noreferrer"` で新しいタブに開く |
| Bladeの出力 | `{{ }}` でエスケープ。`{!! !!}` は `Markdown::toHtml` の戻り値（`HtmlString`）にのみ使う |
| フォーム | CSRFトークン（Laravel標準） |
| 起動 | `php artisan serve` は `127.0.0.1` でのみ待ち受ける（`--host` を変えない） |
| 外部ファイル | CSS・JSは同梱し、外部から読み込まない |

## 7. 実装の順序

計画書 10章の「実装」を以下の順で行う。各ステップでテストとLarastanを通してから次へ進む。

1. 環境構築: Laravelプロジェクト作成、Pint・Larastan・Pest・CI設定、DBファイルの分離と保護（4章）
2. DB: マイグレーション、モデル、ファクトリ
3. 教材の取り込み: CMD-01（パーサ → 検証 → 取り込み）とテスト用の教材フィクスチャ
4. 復習ロジック: ReviewScheduler、NextQuestionPicker、ChoiceShuffler（単体テスト）
5. レッスン受講: SCR-03、CompleteLesson
6. 復習: SCR-04、ReviewQueue、RecordReviewAnswer
7. ホーム・カリキュラム: SCR-01、SCR-02
8. 第1章の教材を生成して取り込み、学習を始める
