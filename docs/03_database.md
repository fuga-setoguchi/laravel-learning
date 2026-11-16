# DB設計書

作成日: 2026-11-16
ステータス: 確定（2026-11-16）
参照: [要件定義書](01_requirements.md) / [教材フォーマット仕様書](04_content-format.md)

## 1. 方針

- DBはPostgreSQL 18。外部キー制約は常に有効
- テーブルを「教材」と「学習記録」に分ける。取り込み（CMD-01）が書き込むのは教材のテーブルだけで、学習記録のテーブルには触れない
- 教材のレッスン・問題は、教材ファイルに書かれた固定のid（文字列）を主キーにする。取り込み直しても同じ行を更新するため、学習記録の外部キーが保たれる
- 教材のレッスン・問題は物理削除しない。ファイルから消えたものは `retired_at` を設定して引退させる
- 日時はAsia/Tokyoで保存する。日付だけで扱う値（次回の復習日、回答日）は `date` 型とする
- 日付の列は `date` 型で、Eloquentでは `immutable_date` にキャストする。比較は日付型として行われるため、`next_review_on <= 今日` で期限当日も含まれる（クエリには `$today->toDateString()` を渡す）
- 日時の列は `timestamp(0) without time zone`（Laravelの `timestamps()` の既定）。Asia/Tokyo の値をそのまま保存する
- 値の範囲・列挙値は、アプリの検証に加えてDBの CHECK 制約でも守る（下表の「制約」）。Laravel 13 のスキーマビルダーには CHECK 制約のメソッドがないため、次のように書く
  - 列挙値: `$table->enum('type', QuestionType::values())`。PostgreSQL では `varchar(255)` + `CHECK (type IN (...))` になる。値の一覧はEnumから取り、二重管理しない
  - 範囲: `DB::statement('ALTER TABLE review_states ADD CONSTRAINT review_states_stage_check CHECK (stage BETWEEN 1 AND 6)')`
- 外部キーの削除時の動作: 出典・選択肢（教材の子で、取り込みで作り直すもの）は `cascadeOnDelete`。学習記録からの参照は既定（削除を拒否）とし、教材の行が消されて学習記録が巻き込まれることを防ぐ
- 列挙値は文字列で保存し、PHPのBacked Enumでキャストする

## 2. ER図

```
【教材】                                      【学習記録】
chapters 1─* lessons 1─* lesson_sources
                │
                ├─0..1 lesson_progress
                │
                1─* questions 1─* question_choices
                       │
                       ├─0..1 review_states
                       └─*    review_logs
```

## 3. テーブル定義

### chapters（章）

| カラム | 型 | NULL | 制約・既定値 | 説明 |
| --- | --- | --- | --- | --- |
| id | integer | × | PK | 章番号（1〜）。`chapters.yaml` の `number` |
| title | string | × | | 章のタイトル |
| retired_at | datetime | ○ | | `chapters.yaml` から消された日時。NULLなら有効 |
| created_at / updated_at | datetime | ○ | | |

章番号をそのまま主キーにする（章番号は教材のidの一部であり、変わらないため）。

### lessons（レッスン）

| カラム | 型 | NULL | 制約・既定値 | 説明 |
| --- | --- | --- | --- | --- |
| id | string(16) | × | PK | 教材のid（例: `ch03-l02`） |
| chapter_id | integer | × | FK → chapters.id | |
| position | integer | × | | 章内の順番（front matter の `order`） |
| title | string | × | | |
| body | text | × | | 本文（Markdown） |
| laravel_version | string(16) | × | | 例: `13.x` |
| pre_question | jsonb | × | | 事前の問い（4章） |
| retired_at | datetime | ○ | | 引退した日時。NULLなら有効 |
| created_at / updated_at | datetime | ○ | | |

- 一意制約: `(chapter_id, position)`（`id` が章・順番から決まるため、引退したレッスンとも重ならない）

### lesson_sources（出典）

| カラム | 型 | NULL | 制約・既定値 | 説明 |
| --- | --- | --- | --- | --- |
| id | integer | × | PK（自動採番） | |
| lesson_id | string(16) | × | FK → lessons.id | |
| position | integer | × | | 表示順 |
| title | string | × | | 例: `Service Container` |
| url | string(255) | × | | `https://laravel.com/docs/13.x/container` |

取り込みのたびにレッスン単位で削除して作り直す（学習記録から参照されないため）。

### questions（問題）

| カラム | 型 | NULL | 制約・既定値 | 説明 |
| --- | --- | --- | --- | --- |
| id | string(24) | × | PK | 問題の固定id（例: `ch03-l02-c1`、`ch03-l02-q1`） |
| lesson_id | string(16) | × | FK → lessons.id | |
| type | enum（varchar + CHECK） | × | CHECK（列挙値） | `recall` / `choice` / `code_review`（`QuestionType`） |
| position | integer | × | | レッスン内の順番（想起カード → 問題の順） |
| prompt | text | × | | 問題文（Markdown） |
| code | text | ○ | | AIコードレビュー問題のコード。それ以外はNULL |
| code_language | string(16) | ○ | | `code` の言語（既定 `php`） |
| answer | text | ○ | | 想起カードの答え（Markdown）。選択式はNULL |
| explanation | text | ○ | | 解説（Markdown）。選択式・AIコードレビュー問題は必須 |
| retired_at | datetime | ○ | | 引退した日時 |
| created_at / updated_at | datetime | ○ | | |

- インデックス: `(lesson_id, position)`
- 想起カードも選択式も、復習の対象として同じ `questions` に入れる（`review_states` / `review_logs` を1つの外部キーで参照するため）
- 事前の問いは復習しないため、`questions` には入れず `lessons.pre_question` に持つ

### question_choices（選択肢）

| カラム | 型 | NULL | 制約・既定値 | 説明 |
| --- | --- | --- | --- | --- |
| id | integer | × | PK（自動採番） | |
| question_id | string(24) | × | FK → questions.id | |
| key | string(1) | × | | 教材に書かれた選択肢の記号（`a`〜`f`） |
| body | text | × | | 選択肢の文（Markdown） |
| is_correct | boolean | × | | 正解かどうか |

- 一意制約: `(question_id, key)`
- 取り込みのたびに問題単位で削除して作り直す。回答履歴は選択肢の行ではなく `key` を保存するため、作り直しても影響しない

### lesson_progress（レッスンの完了状態）

| カラム | 型 | NULL | 制約・既定値 | 説明 |
| --- | --- | --- | --- | --- |
| lesson_id | string(16) | × | PK、FK → lessons.id | |
| completed_at | datetime | × | | 初めて完了した日時。再受講しても変更しない |
| created_at / updated_at | datetime | ○ | | |

行があれば完了済み。

### review_states（問題ごとの復習状態）

| カラム | 型 | NULL | 制約・既定値 | 説明 |
| --- | --- | --- | --- | --- |
| question_id | string(24) | × | PK、FK → questions.id | |
| stage | smallint | × | CHECK（1〜6） | 段階 |
| next_review_on | date | × | | 次回の復習日 |
| last_reviewed_at | datetime | ○ | | 最後に段階を更新した日時（練習は含めない） |
| created_at / updated_at | datetime | ○ | | |

- インデックス: `next_review_on`
- 行があれば復習に登録済み

### review_logs（回答履歴）

| カラム | 型 | NULL | 制約・既定値 | 説明 |
| --- | --- | --- | --- | --- |
| id | integer | × | PK（自動採番） | |
| question_id | string(24) | × | FK → questions.id | |
| mode | enum（varchar + CHECK） | × | CHECK（列挙値） | `scheduled`（期限による出題）/ `practice`（再出題）（`ReviewMode`） |
| result | enum（varchar + CHECK） | × | CHECK（列挙値） | `remembered` / `vague` / `forgot` / `correct` / `incorrect`（`ReviewResult`） |
| choice_key | string(1) | ○ | | 選択式で選んだ選択肢の `key`。想起カードはNULL |
| stage_before | integer | ○ | | 回答前の段階。練習はNULL |
| stage_after | integer | ○ | | 回答後の段階。練習はNULL |
| answered_at | datetime | × | | 回答日時 |
| answered_on | date | × | | 回答日（`answered_at` の日付。今日の回答の検索に使う） |
| created_at / updated_at | datetime | ○ | | |

- インデックス: `(answered_on, question_id)`、`answered_at`
- 計画書の「練習扱いかどうか」は `mode` で表す（真偽値より、出題の種類として読みやすいため）
- `stage_before` / `stage_after` はフェーズ2の学習記録（正答率・段階の推移）で使うため、MVPから保存しておく

## 4. Laravel標準テーブル

| テーブル | 扱い |
| --- | --- |
| users / password_reset_tokens | ログインがないため、作成するマイグレーションを削除する |
| sessions | `SESSION_DRIVER=file` とし、作成しない |
| cache / cache_locks | `CACHE_STORE=file` とし、作成しない |
| jobs / job_batches / failed_jobs | キューを使わないため作成しない |
| migrations | Laravel標準 |

## 5. モデル

| モデル | 主キー | 主なリレーション・スコープ・キャスト |
| --- | --- | --- |
| Chapter | `id`（int、自動採番しない） | `hasMany(Lesson)`。スコープ `active()`（`retired_at IS NULL`） |
| Lesson | `id`（string、`$incrementing = false`、`$keyType = 'string'`） | `belongsTo(Chapter)`、`hasMany(LessonSource)`、`hasMany(Question)`、`hasOne(LessonProgress)`。スコープ `active()`（`retired_at IS NULL`）、`inCurriculumOrder()`（章番号・順番の昇順）。`Lesson::nextToStudy()` で「次に受講するレッスン」（有効で未完了のうち、章番号・順番が最も前のもの。SCR-01・SCR-03・SCR-04 で共通）を返す。ルートモデル結合では引退したレッスンを見つからない扱い（404）にする。`pre_question` は `PreQuestion` 値オブジェクトへキャスト |
| LessonSource | `id` | `belongsTo(Lesson)` |
| Question | `id`（string） | `belongsTo(Lesson)`、`hasMany(QuestionChoice)`、`hasOne(ReviewState)`、`hasMany(ReviewLog)`。スコープ `active()`（問題とレッスンの両方が引退していない）。`type` は `QuestionType` |
| QuestionChoice | `id` | `belongsTo(Question)` |
| LessonProgress | `lesson_id`（string） | `belongsTo(Lesson)` |
| ReviewState | `question_id`（string） | `belongsTo(Question)`。`next_review_on` は `immutable_date` |
| ReviewLog | `id` | `belongsTo(Question)`。`mode` は `ReviewMode`、`result` は `ReviewResult`、`answered_on` は `immutable_date` |

- 全モデルで `Model::shouldBeStrict()`（遅延ロード・存在しない属性・黙って捨てられる代入を例外にする）を開発・テスト時に有効にする
- マスアサインメントは `$fillable` を明示する（`$guarded = []` は使わない）
- 列挙型（`app/Enums`）は、値の一覧を返す `values(): list<string>` を持つ（マイグレーションの CHECK 制約と、検証規則で使う）
- `PreQuestion` のキャストはオブジェクトのキャッシュを無効にする（`withoutObjectCaching`）。jsonb はキーの順や空白を正規化して保存するため、キャッシュした値が保存時に書き戻されると、内容が同じでも「変更あり」と判定される。取り込みでは、事前の問いを値で比べて変わったときだけ設定する
- `PreQuestion`（`app/ValueObjects`）は `Castable` を実装し、`prompt`・`choices`（`Choice` の一覧。`key` と `body`）・`answer`・`explanation` を持つ readonly クラスとする
- ファクトリは全モデルに用意する。学習記録のファクトリには、テストで使う状態（`ReviewState::due()` / `notDue()`、`ReviewLog::scheduled()` / `practice()` / `failedOn()` など）を用意する
