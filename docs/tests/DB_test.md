# DB テスト設計

作成日: 2026-11-16
ステータス: 確定（2026-11-16）
対象: [03_database](../03_database.md)
テストファイル: `tests/Feature/Database/SchemaTest.php`、`tests/Feature/Database/ModelTest.php`

## 1. マイグレーション・制約

| ID | 条件 | 期待結果 |
| --- | --- | --- |
| DB-T01 | マイグレーション後 | 8つのテーブル（chapters, lessons, lesson_sources, questions, question_choices, lesson_progress, review_states, review_logs）がある。Laravel標準の users・sessions・cache・jobs はない |
| DB-T02 | `review_states.stage` に 0 / 7 を保存 | CHECK 制約で失敗する。1 と 6 は保存できる |
| DB-T03 | `questions.type`・`review_logs.mode`・`review_logs.result` に列挙値以外を保存（クエリビルダーで直接） | CHECK 制約で失敗する |
| DB-T04 | `(chapter_id, position)` が同じレッスン / `(question_id, key)` が同じ選択肢 | 一意制約で失敗する |
| DB-T05 | 存在しない `question_id` の `review_states` | 外部キー制約で失敗する |
| DB-T06 | `review_states` がある問題を削除 | 外部キー制約で失敗する（学習記録を巻き込まない） |
| DB-T16 | `lesson_sources.kind` に `source` / `reference` 以外を保存 | CHECK 制約で失敗する |
| DB-T07 | 出典・選択肢を持つレッスン・問題を削除（テストのみで行う操作） | 出典・選択肢も削除される（cascade） |

## 2. モデル

| ID | 条件 | 期待結果 |
| --- | --- | --- |
| DB-T08 | 全モデルのファクトリで1件ずつ作成 | 保存でき、リレーションをたどれる |
| DB-T09 | 引退したレッスン・有効なレッスン | `Lesson::active()` は有効なものだけを返す |
| DB-T10 | 有効なレッスンの引退した問題 / 引退したレッスンの有効な問題 / 両方有効 | `Question::active()` は両方有効なものだけを返す |
| DB-T11 | `pre_question` を `PreQuestion` で保存して読み直す | 同じ値（`prompt`・`choices`・`answer`・`explanation`）に戻る |
| DB-T12 | `next_review_on`・`answered_on` を読む | `CarbonImmutable` の0時（Asia/Tokyo）で、日付が保存した値と一致する |
| DB-T13 | 期限が今日・昨日・明日の `review_states` で `where('next_review_on', '<=', 今日)` | 今日と昨日だけが返る（日付型の比較で期限当日が含まれる） |
| DB-T14 | `fillable` にない属性をまとめて代入 | 例外（`shouldBeStrict`） |
| DB-T15 | 各Enumの `values()` | すべての case の値を、定義順に返す |
