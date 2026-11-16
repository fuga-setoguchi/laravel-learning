# CMD-01 lessons:import テスト設計

作成日: 2026-11-16
ステータス: 確定（2026-11-16）
対象: [CMD-01](../commands/CMD-01_lessons-import.md) / [04_content-format](../04_content-format.md)
テストファイル: `tests/Feature/Commands/ImportLessonsCommandTest.php`、`tests/Unit/Content/LessonFileParserTest.php`

## 1. 正常

| ID | 条件 | 期待結果 |
| --- | --- | --- |
| CMD-01-T01 | 空のDBに `valid/`（2章・3レッスン）を取り込み | 終了コード0。章・レッスン・出典・問題・選択肢が教材どおりに作られる。結果表示の件数が正しい |
| CMD-01-T02 | 同じ教材を2回取り込み | 2回目は「変更なし」のみ。行数が変わらない |
| CMD-01-T03 | 本文・問題文・選択肢・出典を修正して再取り込み（選択肢だけを直した問題、出典だけを直したレッスンを含む） | DBに反映される。選択肢だけ・出典だけの修正も「更新」に数えられる |
| CMD-01-T04 | 学習記録（完了・段階3・回答履歴）がある状態で、教材を修正して再取り込み | `lesson_progress`・`review_states`・`review_logs` が1件も変わらない |
| CMD-01-T05 | 問題を1件削除して再取り込み | その問題の `retired_at` が設定される。`review_states` は残る |
| CMD-01-T06 | T05の後、問題を戻して再取り込み | `retired_at` が NULL に戻り、`review_states` の段階が以前のまま |
| CMD-01-T07 | レッスンファイルを削除して再取り込み | レッスンとその問題がすべて引退する |
| CMD-01-T07b | T07の後、ファイルを戻して再取り込み | レッスンと問題の `retired_at` が NULL に戻り、`lesson_progress`・`review_states` が以前のまま。結果表示の「復活」に数えられる |
| CMD-01-T07c | `chapters.yaml` から章を消して再取り込み（その章のレッスンファイルも削除済み） / 章を戻して再取り込み | 章が引退する / 引退が解除される |
| CMD-01-T08 | 問題の順番を入れ替えて再取り込み | `position` が記載順に更新される。idと学習記録は変わらない |
| CMD-01-T09 | `choice` を `code_review` に変更（code追加） | 取り込める。種別が更新される |
| CMD-01-T10 | `--dry-run` | 終了コード0、「--dry-run」の表示と件数。DBは変わらない |
| CMD-01-T11 | 想起カードが2件（推奨外）/ AIコードレビュー問題が0件 | 警告が表示され、取り込みは成功する |
| CMD-01-T12 | `content/` 直下の `README.md`、`ch01/notes.txt` | 無視される |
| CMD-01-T13 | 出典URLにアンカー（`#binding`）付き | 取り込める |

## 2. 検証エラー（すべて終了コード1、DBは変更されない）

| ID | 条件 | 期待されるエラー項目 |
| --- | --- | --- |
| CMD-01-T14 | front matter がない / YAMLの構文エラー | ファイル名と「front matter」の解析エラー |
| CMD-01-T15 | 必須項目の欠落（データセット: id, chapter, order, title, laravel_version, sources, pre_question, recall_cards, questions, 本文、`recall_cards.0.id`、`questions.0.id`、`sources.0.url`、`pre_question.answer`） | 該当項目 |
| CMD-01-T16 | `sources` が空配列 | `sources` |
| CMD-01-T17 | 出典URLが `https://laravel.com/docs/` 以外（`http://`、別ドメイン、`laravel.com.evil.example`） | `sources.0.url` |
| CMD-01-T18 | 出典URLのバージョンが laravel_version と不一致（12.x ⇔ 13.x） | `sources.0.url` |
| CMD-01-T19 | 出典URLがバージョンのみ（`https://laravel.com/docs/13.x/`） | `sources.0.url` |
| CMD-01-T20 | `laravel_version` が数値（引用符なしの `13.0`）/ `13` | `laravel_version` |
| CMD-01-T21 | idとファイル位置の不一致、chapter・order と id の不一致 | `id` / `chapter` / `order` |
| CMD-01-T22 | 問題idの接頭辞がレッスンidと違う、想起カードに `-q` を付けた | `recall_cards.0.id` / `questions.0.id` |
| CMD-01-T23 | `answer` が choices にない、`key` の重複、選択肢が1件・7件 | 該当項目 |
| CMD-01-T24 | `code_review` に `code` がない / `choice` に `code` がある | `questions.0.code` |
| CMD-01-T25 | 同じファイルの中で問題idが重複（想起カード2枚が同じid） | `recall_cards.1.id`（重複） |
| CMD-01-T26 | `chapters.yaml` の `number` が重複 / `title` がない | `chapters.0.number` など |
| CMD-01-T27 | `chapters.yaml` にない章 | `chapter` |
| CMD-01-T28 | `chapters.yaml` がない / 教材フォルダがない | エラーメッセージ |
| CMD-01-T29 | 想起カードのid（`-c1`）を `questions` に移した | `questions.0.id`（接頭辞の不一致。想起カード ⇔ 選択式の種別変更はこれで防がれる） |
| CMD-01-T30 | 2ファイルに別々のエラー | 両方のエラーが一度に表示される |
| CMD-01-T31 | 1ファイルにエラー、他は正常 | 正常なファイルも取り込まれない（全件不成立） |
| CMD-01-T35 | エラーのある教材で `--dry-run` | 終了コード1、エラーの一覧 |
| CMD-01-T36 | `code_language` が許可されていない値（`ruby`） | `questions.0.code_language` |
| CMD-01-T37 | レッスンごとに `laravel_version` が異なる（13.x と 12.x） | 警告が表示され、取り込みは成功する |

## 3. LessonFileParser（単体）

| ID | 条件 | 期待結果 |
| --- | --- | --- |
| CMD-01-T32 | 正常なファイル | front matter の配列と本文に分かれる。本文の先頭の空行は除く |
| CMD-01-T33 | 本文中に `---` がある | 最初の front matter だけを解析し、本文中の `---` は本文として残る |
| CMD-01-T34 | CRLF改行 | LFと同じ結果 |
