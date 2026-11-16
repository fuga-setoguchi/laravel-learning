# SCR-04 復習

作成日: 2026-11-16
ステータス: 確定（2026-11-16）
対応要件: FR-09, FR-10, FR-11, FR-12
関連: [05_review-logic](../05_review-logic.md)

## 1. 概要

| 項目 | 内容 |
| --- | --- |
| Controller | `ReviewController`（`show` / `store` / `result`） |
| ビュー | `resources/views/review/{question,result,done}.blade.php` |
| FormRequest | `SubmitReviewAnswerRequest` |
| 目的 | 次に出す問題を1問ずつ出題し、回答・自己評価を受け付ける |

| メソッド・パス | 名前 | 内容 |
| --- | --- | --- |
| `GET /review` | `review.show` | 次の問題、または完了画面 |
| `POST /review/answers` | `review.answers.store` | 回答の記録 |
| `GET /review/answers/{reviewLog}` | `review.answers.show` | 選択式の正誤と解説 |

## 2. 出題画面（GET /review）

`ReviewQueue` で次の問題を1問決めて表示する。問題がなければ完了画面を表示する。

### 2.1 共通

```
┌──────────────────────────────────────────────┐
│ Laravel学習      [ホーム] [カリキュラム]          │
├──────────────────────────────────────────────┤
│ ※フラッシュメッセージ（記録しなかった理由など）       │
│ 今日の復習  残り 12 問（うち再出題 2 問）          │
│ 第3章 / 3-2 なぜコントローラーで… / 段階 2          │
│ [再出題] ← 練習のときだけ表示（結果は段階に影響しない）│
│ （種別ごとの本体）                                │
└──────────────────────────────────────────────┘
```

| No | 項目 | 内容 |
| --- | --- | --- |
| 1 | 残り件数 | [05_review-logic](../05_review-logic.md) 4.3 |
| 2 | 出典 | 章・レッスン番号・レッスンのタイトル（レッスンへのリンクにはしない。答えを見に行けてしまうため）、現在の段階 |
| 3 | 再出題の表示 | `mode = practice` のとき「再出題（練習）」と表示する |
| 4 | hidden項目 | `question_id`、`mode`、`last_log_id`（[05_review-logic](../05_review-logic.md) 7.1） |

### 2.2 想起カード

```
│ Q. コンストラクタで依存を受け取ると、テストで…        │
│ 頭の中で答えてから開いてください                     │
│ ▶ 答えを見る                                    │
│   （開くと答えと評価ボタンが現れる）                  │
│   [思い出せた] [あいまい] [思い出せなかった]          │
```

| No | 項目 | 内容 |
| --- | --- | --- |
| 1 | 問題文 | `prompt`（Markdown） |
| 2 | 答え | `<details>` 内に `answer`（Markdown） |
| 3 | 評価ボタン | `<details>` の中に置く（答えを開く前に評価させない）。`<button name="rating" value="remembered">` など3つ。送信先は `review.answers.store` |

- 送信後は `review.show` へリダイレクトし、次の問題を表示する（想起カードには結果画面がない）

### 2.3 選択式・AIコードレビュー問題

```
│ Q. AIが書いた次のコントローラーの問題点として…        │
│ ┌──────────────────────────────┐               │
│ │ public function store(...)    │ ← code_review のみ│
│ └──────────────────────────────┘               │
│  ( ) 1. …                                      │
│  ( ) 2. …                                      │
│  ( ) 3. …                                      │
│ [回答する]                                      │
```

| No | 項目 | 内容 |
| --- | --- | --- |
| 1 | 問題文 | `prompt`（Markdown） |
| 2 | コード | `code_review` のみ。`<pre><code class="language-{code_language}">` |
| 3 | 選択肢 | ラジオボタン（`name="choice_key"`、値は `key`、`required`）。表示順は ChoiceShuffler、番号は表示順 |
| 4 | 回答する | `review.answers.store` へPOST |

## 3. 回答の記録（POST /review/answers）

| 項目 | 規則（SubmitReviewAnswerRequest） |
| --- | --- |
| `question_id` | 必須、文字列、`exists:questions,id` |
| `mode` | 必須、`ReviewMode` の値 |
| `last_log_id` | 必須、0以上の整数 |
| `rating` | 任意、`remembered` / `vague` / `forgot` |
| `choice_key` | 任意、`a`〜`f` |

`RecordReviewAnswer` の結果に応じてリダイレクトする。

| 結果 | リダイレクト先 | フラッシュ |
| --- | --- | --- |
| 記録した（想起カード） | `review.show` | なし |
| 記録した（選択式） | `review.answers.show`（記録した `review_logs.id`） | なし |
| 記録しなかった（期限前・二重送信） | `review.show` | 「この問題はすでに回答済みのため、記録しませんでした」 |
| 記録しなかった（練習の対象外・練習で正解済み） | `review.show` | 「この問題の再出題はすでに終わっているため、記録しませんでした」 |
| 記録しなかった（`last_log_id` の不一致） | `review.show` | 「この画面の後に回答が記録されているため、記録しませんでした」 |
| 記録しなかった（引退・未登録） | `review.show` | 「この問題は復習の対象ではないため、記録しませんでした」 |
| 種別と入力の組み合わせ不正、存在しない `choice_key` | 直前の画面（バリデーションエラー） | エラーメッセージ |
| 存在しない `question_id` | 直前の画面（バリデーションエラー） | エラーメッセージ |

## 4. 正誤と解説の画面（GET /review/answers/{reviewLog}）

```
│ 正解 ✓ / 不正解 ✗                               │
│ Q. （問題文。code_review はコードも）              │
│  1. …  ← あなたの答え                            │
│  2. …  ← 正解                                   │
│ 解説: …                                         │
│ 次回の復習: 2026-12-04（段階 2）  ← scheduled のみ │
│ 不正解の問題は、正解するまで今日の最後に出題します ← 評価が fail のとき（練習を含む） │
│ 対象: Laravel 13.x  出典: … ↗                    │
│ [次へ]                                          │
```

| No | 項目 | 内容 |
| --- | --- | --- |
| 1 | 正誤 | `result`。文字と記号を併記する（色だけで区別しない） |
| 2 | 選択肢 | ChoiceShuffler で、回答した日（`answered_on`）をシードにした順に表示する（出題時と同じ順）。あなたの答え（`choice_key`）と正解を示す |
| 3 | 解説 | `explanation`（Markdown） |
| 4 | 次回の復習 | `mode = scheduled` のとき、`stage_after` と、`answered_on` + `stage_after` の日数（`ReviewScheduler::INTERVALS`）。`review_states` は読まない（後で状態が変わっても、この回答時点の結果を表示するため） |
| 5 | 出典 | レッスンの対象バージョン・出典（ここでは答えを見た後なので表示してよい） |
| 6 | 次へ | `review.show` へのリンク |

- 想起カードの `review_logs` を指定した場合は404（結果画面は選択式のみ）
- 問題が後から引退していても、結果画面は表示する（過去の回答の記録のため）
- 0時をまたいで回答した場合、選択肢の並び順が出題時と異なることがある（許容する）
- 状態を変更しないため、再読み込みしても問題ない

## 5. 完了画面

```
│ 今日の復習は完了しました                           │
│ 今日の回答: 14 問（正解・思い出せた 10 / あいまい 2 / 不正解・思い出せなかった 2）│
│ [ホームへ]  次のレッスン: 3-3 …                   │
```

| No | 項目 | 内容 |
| --- | --- | --- |
| 1 | 今日の回答 | 今日の `mode = scheduled` の `review_logs` を評価（pass / hold / fail）ごとに集計。0件なら「今日復習する問題はありませんでした」 |
| 2 | 次のレッスン | SCR-01 と同じ規則。なければ表示しない |
