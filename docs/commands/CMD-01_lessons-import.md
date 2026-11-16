# CMD-01 lessons:import

作成日: 2026-11-16
ステータス: 確定（2026-11-16）
対応要件: FR-01, FR-02, FR-03, FR-04
関連: [04_content-format](../04_content-format.md) / [03_database](../03_database.md)

## 1. 概要

| 項目 | 内容 |
| --- | --- |
| コマンド | `php artisan lessons:import {--path=} {--dry-run}` |
| クラス | `App\Console\Commands\ImportLessonsCommand` |
| 処理の委譲先 | `LessonFileParser` → `LessonFileValidator` → `ContentImporter` |
| 目的 | `content/` の教材を検証し、DBの教材テーブルに追加・更新・引退を反映する。学習記録には触れない |

| オプション | 既定 | 内容 |
| --- | --- | --- |
| `--path` | `config('learning.content_path')`（= `base_path('content')`） | 教材フォルダ |
| `--dry-run` | なし | 検証と差分の集計だけ行い、DBに書き込まない |

## 2. 処理の流れ

```
1. 読み込み  chapters.yaml と chNN/lNN.md をすべて読む（ファイル名順）
2. 解析      front matter（YAML）と本文に分ける
3. 検証      ファイル単位の検証 → ファイルをまたぐ検証
             1件でもエラーがあれば、エラーを一覧表示して終了コード1（DBは変更しない）
4. 反映      1つのトランザクションで、章 → レッスン → 出典 → 問題 → 選択肢 → 引退 の順に反映
5. 結果表示  件数を表示して終了コード0
```

- 検証はすべてのファイルについて行い、最初のエラーで止めない（一度に直せるように）
- 反映はすべて成功するか、まったく反映しないかのどちらか（トランザクション）

## 3. 検証

[04_content-format](../04_content-format.md) 2〜3章の規則をすべて検証する。実装は以下のとおり。

| 段階 | 内容 | 実装 |
| --- | --- | --- |
| 解析 | front matter がない、YAMLの構文エラー、front matter が連想配列でない | `LessonFileParser`。YAMLの例外を捕まえてエラーにする |
| ファイル単位 | 必須項目、型、正規表現、件数、`answer` が `choices` の `key` にあるか、`key` の重複、出典URLの形式とバージョン、idとファイル位置の一致、問題idの接頭辞 | `LessonFileValidator`。Laravel の `Validator::make()` の規則と、独自の規則クラス（`LaravelDocsUrl` など）、`after()` フック |
| ファイルをまたぐ | 問題idの重複、`chapters.yaml` の `number` の重複、`chapter` が `chapters.yaml` にあるか（レッスンidと `order` はファイル位置との一致で一意になる） | `LessonFileValidator` |

次の場合は、エラーではなく警告として表示し、取り込みは行う。

- 推奨件数（想起カード3〜5、問題1〜2）を外れる
- AIコードレビュー問題（`code_review`）がない（計画書 5章「各レッスンにAIコードレビュー問題を含める」）
- レッスンによって `laravel_version` が異なる

### エラーの表示形式

```
ERROR  教材の検証で 3 件のエラーがありました。DBは変更していません。

  content/ch03/l02.md
    sources.0.url: URLのバージョン（12.x）が laravel_version（13.x）と一致しません
    questions.1.answer: choices に存在しない key（e）です
  content/ch03/l03.md
    id: ファイルの位置（ch03-l03）と一致しません（ch03-l04）
```

- 項目はドット区切りのパス（`questions.1.answer`、0始まり）で示す
- メッセージは日本語。`lang/ja/validation.php` と独自のメッセージで定義する

## 4. 反映の規則

| 対象 | 照合キー | ファイルにある | ファイルにない |
| --- | --- | --- | --- |
| chapters | `number` | 追加・更新（`title`）。`retired_at` を NULL に戻す | `retired_at` を設定（その章を指すレッスンファイルがあれば、検証の段階でエラー） |
| lessons | `id` | 追加・更新。`retired_at` があれば NULL に戻す | `retired_at` を設定（すでに設定済みなら変更しない） |
| lesson_sources | レッスン単位 | レッスンの出典をすべて削除して作り直す | （レッスンの引退に従う） |
| questions | `id` | 追加・更新。`position` はファイル内の記載順（想起カード → 問題）。`retired_at` を NULL に戻す | `retired_at` を設定 |
| question_choices | 問題単位 | 問題の選択肢をすべて削除して作り直す | （問題の引退に従う） |
| lesson_progress / review_states / review_logs | — | 変更しない | 変更しない |

- 更新は `updateOrCreate` などで行い、`created_at` を保つ
- レッスンが引退した場合、その問題もすべて引退させる
- 引退した問題が復活した場合、`review_states` は残っているので以前の段階から復習が再開する。次回の復習日が過去なら、期限が来た問題として出題される
- 反映の前に、取り込み対象の教材を検証済みの値オブジェクト（`ParsedLesson` など）に変換しておき、反映処理では配列を扱わない

## 5. 結果表示

```
教材を取り込みました。
  章:     追加 1 / 更新 0
  レッスン: 追加 3 / 更新 1 / 変更なし 10 / 引退 0 / 復活 0
  問題:    追加 15 / 更新 2 / 変更なし 50 / 引退 1 / 復活 0
WARN  content/ch01/l03.md: recall_cards が 2 件です（推奨 3〜5 件）
```

- 「更新」はDBの値が実際に変わったもの、「変更なし」は同じ内容だったもの
  - レッスン: 行の変更（`wasChanged()`）、または出典の一覧（タイトル・URL・順番）が変わった場合に「更新」
  - 問題: 行の変更、または選択肢の一覧（`key`・本文・正解）が変わった場合に「更新」
  - 出典・選択肢は、作り直す前に既存の一覧と比較し、同じなら作り直さない
- `--dry-run` のときは先頭を「検証に成功しました（--dry-run のため、DBは変更していません）」とし、同じ件数を表示する。件数の計算は、トランザクション内で反映してから最後にロールバックすることで求める（集計のための別ロジックを作らない）

## 6. 終了コード

| コード | 条件 |
| --- | --- |
| 0 | 取り込み成功（警告があっても0） |
| 1 | 検証エラー、教材フォルダ・`chapters.yaml` が存在しない |

## 7. 補足

- 学習用DBへの取り込み前にバックアップを取る（[02_architecture](../02_architecture.md) 4.3）
- 取り込みはマイグレーション済みのDBが前提。テーブルがない場合はLaravelの例外のまま終了する
