# 復習ロジック テスト設計

作成日: 2026-11-16
ステータス: 確定（2026-11-16）
対象: [05_review-logic](../05_review-logic.md)

## 1. ReviewScheduler（単体）

テストファイル: `tests/Unit/Review/ReviewSchedulerTest.php`。今日 = 2026-12-01。

| ID | 条件 | 期待結果 |
| --- | --- | --- |
| LOGIC-T01 | `initial()` | 段階1、2026-12-02 |
| LOGIC-T02 | 段階1〜5 × pass（データセット） | 段階+1、今日 + 新しい段階の日数（3, 7, 14, 30, 60日） |
| LOGIC-T03 | 段階6 × pass | 段階6、2027-01-30 |
| LOGIC-T04 | 段階1〜6 × hold（データセット） | 段階そのまま、今日 + その段階の日数 |
| LOGIC-T05 | 段階1〜6 × fail（データセット） | 段階1、2026-12-02 |
| LOGIC-T06 | 段階0、段階7 | `InvalidArgumentException` |
| LOGIC-T07 | 月末・年末をまたぐ（今日 = 2026-12-31、段階1 pass） | 2027-01-03 |
| LOGIC-T49 | 今日として時刻つきの値（2026-12-01 23:59:59）を渡す | 次回の復習日は 2026-12-02 00:00:00（日付だけで扱う） |

## 2. ReviewResult → Grade（単体）

| ID | 条件 | 期待結果 |
| --- | --- | --- |
| LOGIC-T08 | 5つの結果（データセット） | remembered・correct → pass、vague → hold、forgot・incorrect → fail |

## 3. NextQuestionPicker（単体）

テストファイル: `tests/Unit/Review/NextQuestionPickerTest.php`。候補は（id, 章）で表す。

| ID | 候補（並び順どおり） | 直前の章 | 期待結果 |
| --- | --- | --- | --- |
| LOGIC-T09 | なし | 1 | null |
| LOGIC-T10 | (a,1) (b,2) | null | a |
| LOGIC-T11 | (a,1) (b,2) | 1 | b |
| LOGIC-T12 | (a,1) (b,1) (c,2) | 1 | c |
| LOGIC-T13 | (a,1) (b,1) | 1 | a（すべて同じ章なら先頭） |
| LOGIC-T14 | (a,2) (b,1) | 1 | a |

## 4. ChoiceShuffler（単体）

| ID | 条件 | 期待結果 |
| --- | --- | --- |
| LOGIC-T15 | 同じ問題id・同じ日で2回 | 同じ順 |
| LOGIC-T16 | 同じ問題idで日付を変えて複数回 | すべてが同じ順にはならない（10日分で2種類以上の順） |
| LOGIC-T17 | 任意の入力 | 出力は入力と同じ要素の並べ替え（欠落・重複なし） |

## 5. ReviewQueue（機能）

テストファイル: `tests/Feature/Review/ReviewQueueTest.php`。今日 = 2026-12-01。

| ID | 条件 | 期待結果 |
| --- | --- | --- |
| LOGIC-T18 | 期限が 11-30、12-01、12-02 の問題（ファクトリでモデル経由で保存） | 候補Aは 11-30、12-01 の2件（期限当日を含み、12-02 は含まない）。11-30 が先 |
| LOGIC-T19 | 期限が来た問題のレッスンが引退 / 問題が引退 | 候補に含まれない |
| LOGIC-T20 | 今日の最新の回答が第1章。候補Aの先頭が第1章、2番目が第2章 | 第2章の問題を返す |
| LOGIC-T21 | 今日の回答なし（昨日の最新の回答が第1章）。候補Aの先頭が第1章 | 第1章の問題を返す（前日の回答は考慮しない） |
| LOGIC-T22 | 候補Aなし。今日 scheduled で fail の問題が2件（回答順 x, y）、練習なし | x を practice として返す |
| LOGIC-T23 | 候補Aが1件、Bが1件 | Aの問題を scheduled として返す |
| LOGIC-T23b | 候補AなしでBが（第1章、第2章）の順、今日の最新の回答が第1章 | 第2章の問題を practice として返す（章を避ける規則をBにも適用） |
| LOGIC-T24 | 今日 fail → practice で正解 | Bに含まれない |
| LOGIC-T24b | 今日 fail → practice で不正解 | Bに含まれる。並び順は最新の回答日時（後ろに回る） |
| LOGIC-T24c | 想起カードが今日 forgot → practice で vague | Bに含まれない（fail 以外で終わる） |
| LOGIC-T25 | 今日 scheduled で vague（hold） | Bに含まれない（再出題は fail のみ） |
| LOGIC-T26 | 昨日 fail で練習していない（期限は今日） | Bには含まれず、Aとして出る |
| LOGIC-T27 | AもBもなし | null（完了） |
| LOGIC-T28 | 残り件数 | A 3件・B 2件で「残り5、うち再出題2」 |
| LOGIC-T29 | 候補が50件 | クエリ数が候補5件のときと同じ |
| LOGIC-T50 | 今日の復習の状態（データセット: 残りあり / 未登録 / 完了 / 予定なし / 引退した問題だけ登録あり → 未登録） | 05_review-logic 4.4 の判定どおりの状態と、次回の復習日（今日より後で最も早い日。引退した問題は除く） |
| LOGIC-T29b | 次の問題を取得 | レッスン・章・選択肢・復習状態を読み込み済みで、その問題の最新の回答のid（`last_log_id` に使う）が付いている |

## 6. CompleteLesson（機能）

| ID | 条件 | 期待結果 |
| --- | --- | --- |
| LOGIC-T30 | 未完了のレッスン（問題5件）を完了 | `lesson_progress` が作られ、5件の `review_states` が段階1・翌日で作られる。戻り値は登録5件・初回 |
| LOGIC-T31 | 完了済みのレッスンを再度完了（段階3の問題あり） | `completed_at` と既存の `review_states` は変わらない。登録0件 |
| LOGIC-T32 | 完了済みのレッスンに問題が1件追加された後に再度完了 | 追加の1件だけが段階1・翌日で登録される |
| LOGIC-T33 | 引退した問題を含むレッスンを完了 | 引退した問題は登録されない |

## 7. RecordReviewAnswer（機能）

今日 = 2026-12-01。

| ID | 条件 | 期待結果 |
| --- | --- | --- |
| LOGIC-T34 | 期限が来た想起カード（段階2）に remembered | `review_logs` 1行（scheduled、stage 2→3）。`review_states` が段階3・12-08 |
| LOGIC-T35 | 期限が来た選択式に正解の key | result = correct、段階+1 |
| LOGIC-T36 | 期限が来た選択式に不正解の key | result = incorrect、段階1・12-02、`choice_key` が保存される |
| LOGIC-T37 | 期限前（12-02）の問題に scheduled で回答 | Rejected。DB変更なし |
| LOGIC-T38 | 同じ回答を2回連続で実行（二重送信、同じ `last_log_id`） | 2回目は Rejected。`review_logs` は1行 |
| LOGIC-T39 | 今日 fail の問題に practice で回答 | `review_logs` に practice の行（stage_before/after はNULL）。`review_states` は変更なし |
| LOGIC-T40 | practice で正解済みの問題に再度 practice | Rejected |
| LOGIC-T40b | practice で不正解の直後、同じ `last_log_id` で再送信 | Rejected（`last_log_id` の不一致）。`review_logs` は増えない |
| LOGIC-T40c | practice で2回不正解の後、正しい `last_log_id` で practice に正解 | 記録される（回数の上限はない） |
| LOGIC-T41 | 今日 fail していない問題に practice | Rejected |
| LOGIC-T42 | 今日 fail の問題に scheduled で再送信（戻るボタン） | Rejected（期限前）。練習として記録されない |
| LOGIC-T43 | 引退した問題、`review_states` がない問題 | Rejected |
| LOGIC-T44 | 想起カードに choice_key / 選択式に rating / 存在しない key | ValidationException。DB変更なし |
| LOGIC-T45 | 期限切れ（11-25 期限、段階4）に pass | 次回は回答日基準で 12-01 + 30日 = 12-31 |
| LOGIC-T46 | 12-01 23:59 に期限が 12-01 の問題を表示し、12-02 00:01 に scheduled で回答 | 受け付けられ、12-02 を基準に次回の復習日が決まる |
| LOGIC-T47 | 12-01 に fail、12-02 00:01 に practice で回答 | Rejected（再出題は当日のみ） |
| LOGIC-T48 | 回答の形式不正（想起カードに choice_key）かつ期限前の問題 | ValidationException（形式の検証を受け付けの判定より先に行う） |
