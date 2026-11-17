# 復習ロジック設計書

作成日: 2026-11-16
ステータス: 確定（2026-11-16）
参照: [プロジェクト計画書](00_project-plan.md) 6章 / [要件定義書](01_requirements.md) / [DB設計書](03_database.md)

## 1. 概要

| クラス | 責務 | DB |
| --- | --- | --- |
| `App\Review\ReviewScheduler` | 段階と結果から、新しい段階と次回の復習日を計算する | 使わない |
| `App\Review\NextQuestionPicker` | 候補の一覧と直前の章から、次の1問を選ぶ | 使わない |
| `App\Review\ChoiceShuffler` | 選択肢の表示順を決める | 使わない |
| `App\Review\ReviewQueue` | DBから候補を集め、次の問題と残り件数を返す | 読み取りのみ |
| `App\Actions\CompleteLesson` | レッスンを完了し、問題を復習に登録する | 書き込み |
| `App\Actions\RecordReviewAnswer` | 回答を受け付けてよいか判定し、記録・段階の更新を行う | 書き込み |

純粋なクラスは「今日」を引数で受け取り、`now()` / `today()` を呼ばない。単体テストで日付を自由に与えるためである。

## 2. 結果と評価

| 問題の種別 | 結果（`ReviewResult`） | 評価（`Grade`） |
| --- | --- | --- |
| 想起カード | `remembered`（思い出せた） | `pass` |
| 想起カード | `vague`（あいまい） | `hold` |
| 想起カード | `forgot`（思い出せなかった） | `fail` |
| 選択式・AIコードレビュー | `correct`（正解） | `pass` |
| 選択式・AIコードレビュー | `incorrect`（不正解） | `fail` |

`ReviewResult::grade(): Grade` で変換する。選択式に `hold` はない。

## 3. ReviewScheduler

### 3.1 間隔

| 段階 | 1 | 2 | 3 | 4 | 5 | 6 |
| --- | --- | --- | --- | --- | --- | --- |
| 次回までの日数 | 1 | 3 | 7 | 14 | 30 | 60 |

定数として `ReviewScheduler::INTERVALS = [1 => 1, 2 => 3, 3 => 7, 4 => 14, 5 => 30, 6 => 60]` に持つ。

### 3.2 インターフェース

```php
final class ReviewScheduler
{
    /** レッスン完了時の初期状態: 段階1、翌日 */
    public function initial(CarbonImmutable $today): ScheduledState;

    /** 回答後の状態 */
    public function next(int $stage, Grade $grade, CarbonImmutable $today): ScheduledState;
}

final readonly class ScheduledState
{
    public function __construct(public int $stage, public CarbonImmutable $nextReviewOn) {}
}
```

### 3.3 更新ルール

| 評価 | 新しい段階 | 次回の復習日 |
| --- | --- | --- |
| `pass` | `min(stage + 1, 6)` | 今日 + 新しい段階の日数 |
| `hold` | `stage`（そのまま） | 今日 + その段階の日数 |
| `fail` | 1 | 今日 + 1日 |

- 次回の復習日は、元の期限日ではなく **回答した日** を基準にする（期限切れの問題を数日遅れで正解した場合も、回答日から数える）
- `stage` が1〜6の範囲外なら `InvalidArgumentException`

例（今日 = 2026-12-01）:

| 回答前の段階 | 評価 | 新しい段階 | 次回の復習日 |
| --- | --- | --- | --- |
| 1 | pass | 2 | 2026-12-04 |
| 1 | hold | 1 | 2026-12-02 |
| 3 | fail | 1 | 2026-12-02 |
| 5 | pass | 6 | 2027-01-30 |
| 6 | pass | 6 | 2027-01-30 |
| 6 | hold | 6 | 2027-01-30 |

## 4. 次の問題の決め方

出題順はセッションに保持せず、`GET /review` のたびにDBの状態から1問を決める。

### 4.1 候補

| 候補 | 条件 | 並び順（基本） |
| --- | --- | --- |
| A. 期限が来た問題 | `review_states.next_review_on <= 今日`、かつ問題・レッスンが引退していない | `next_review_on` 昇順 → 問題の章・レッスン・`position` 昇順 |
| B. 再出題の対象 | 今日（`answered_on = 今日`）に `mode = scheduled` で評価が `fail` の回答があり、かつ今日の最新の回答（練習を含む）の評価が `fail`。かつ引退していない。かつ A に含まれない | 今日の最新の回答の日時の昇順（直前に間違えた問題は後ろに回る） |

- 再出題は、`fail` 以外（選択式は正解、想起カードは「思い出せた」または「あいまい」）になった時点で終わる。何回くり返しても段階と次回の復習日は変わらない
- Aが1件以上あればAから選ぶ。Aが0件のときだけBから選ぶ。どちらも0件なら「今日の復習は完了」
- 不正解の問題は次回の復習日が翌日になるため、その日のうちにAに戻ることはない。「Aに含まれない」は念のための条件である

### 4.2 章を避ける規則（NextQuestionPicker）

```php
final class NextQuestionPicker
{
    /**
     * @param list<Candidate> $candidates 並び順（基本）に並んだ候補
     * @param int|null $lastChapter 直前に回答した問題の章（今日の回答がなければ null）
     */
    public function pick(array $candidates, ?int $lastChapter): ?Candidate;
}
```

1. 候補が空なら `null`
2. `$lastChapter` が `null` なら先頭を返す
3. 先頭から順に見て、章が `$lastChapter` と異なる最初の候補を返す
4. すべて同じ章なら先頭を返す

- 「直前に回答した問題」は、今日の `review_logs` のうち `answered_at` が最新のもの（練習を含む）。日をまたいだ前日の回答は考慮しない
- AとBのどちらにも同じ規則を適用する
- 「期限の古い順」より「章を避ける」を優先する。期限が古い問題が後回しになるのは高々1問分である

### 4.3 残り件数

ホームと復習画面に「残り N 問（うち再出題 M 問）」を表示する。N = Aの件数 + Bの件数、M = Bの件数。ただしBの件数は「今の時点で確定している再出題」の数であり、これから不正解になる問題で増える。

### 4.4 今日の復習の状態

ホーム（SCR-01）と復習の完了画面（SCR-04）は、残りが0件のときの表示を、次の状態で出し分ける。`ReviewQueue::summary(今日)` が、状態・残り件数・今日の回答の集計・次回の復習日をまとめて返す（`TodayReview`）。

| 状態（`TodayReviewStatus`） | 判定（上から順に） | 表示 |
| --- | --- | --- |
| `InProgress`（残りあり） | 残り件数が1以上 | 残り件数（4.3） |
| `NotStarted`（未登録） | 復習に登録済みの有効な問題が0件 | 「まだ復習する問題はありません。レッスンを完了すると、翌日から復習が始まります。」 |
| `Completed`（完了） | 今日の `mode = scheduled` の回答が1件以上 | 「今日の復習は完了しました。」と次回の復習日 |
| `NothingDue`（予定なし） | 上記以外 | 「今日復習する問題はありません。」と次回の復習日 |

- 次回の復習日: 有効な問題の `review_states.next_review_on` のうち、今日より後で最も早い日。なければ表示しない
- 今日の回答の集計（評価ごとの件数）も `TodayReview` に含める（完了画面で使う）
- 練習（`practice`）の回答だけでは「完了」にしない（再出題は期限の出題の後にしか起きないため、実際には `scheduled` の回答が必ず先にある）

## 5. 選択肢の表示順（ChoiceShuffler）

- 選択式・AIコードレビュー問題・事前の問いの選択肢は、`crc32(問題id . 今日の日付)` をシードにした決定的なシャッフルで並べる（`Random\Randomizer` + `Random\Engine\Mt19937`）
- 同じ日・同じ問題なら、再読み込みしても同じ順になる。日が変われば順が変わる
- 事前の問いは `crc32(レッスンid . 今日の日付)` をシードにする
- 画面には教材の `key`（a, b, c…）ではなく、表示順の番号（1, 2, 3…）を表示する（`key` で正解の位置を覚えないため）。送信する値は `key`

## 6. レッスン完了（CompleteLesson）

`handle(Lesson $lesson, CarbonImmutable $now): CompleteLessonResult` をトランザクション内で実行する。

1. `lesson_progress` に行がなければ `completed_at = $now` で作成する。あれば変更しない
2. レッスンの有効な問題（引退していない）のうち、`review_states` に行がないものについて、`ReviewScheduler::initial(今日)`（段階1・翌日）で行を作成する
3. 登録済みの問題の `review_states` は変更しない（再受講で段階がリセットされないように）
4. 戻り値: 新たに登録した問題の件数、初めての完了かどうか

## 7. 回答の記録（RecordReviewAnswer）

### 7.1 入力

| 項目 | 内容 |
| --- | --- |
| `question_id` | 問題のid |
| `mode` | 画面が出題したときの種類（`scheduled` / `practice`）。hiddenで送る |
| `last_log_id` | 画面を表示した時点での、その問題の最新の `review_logs.id`（なければ0）。hiddenで送る |
| `choice_key` | 選択式・AIコードレビュー問題のとき必須 |
| `rating` | 想起カードのとき必須（`remembered` / `vague` / `forgot`） |

判定の順番: (1) FormRequest の形式検証 → (2) Action での種別と入力の組み合わせの検証（`ValidationException`）→ (3) 7.2 の受け付けの判定（`Rejected`）。

FormRequestでは形式（必須・列挙値・問題の存在）だけを検証する。問題の種別と `choice_key` / `rating` の組み合わせ、`choice_key` がその問題の選択肢に存在するかは Action で検証し、不正なら `ValidationException` を投げる。

### 7.2 受け付けの判定

トランザクション内で、問題の状態を読み直してから判定する。`scheduled` の段階の更新は `UPDATE review_states SET ... WHERE question_id = ? AND next_review_on <= 今日` の更新件数で最終判定し、0件なら `Rejected` とする（同時送信でも二重に記録しないため）。

| mode | 受け付ける条件 | 条件を満たさない例 |
| --- | --- | --- |
| `scheduled` | 問題が引退しておらず、`review_states` があり、`next_review_on <= 今日` | 回答済みで期限が先になった（二重送信・戻るボタン）、未登録の問題 |
| `practice` | 問題が引退しておらず、4.1のBの条件を満たす | 練習で正解済み、今日不正解になっていない |
| 共通 | その問題の最新の `review_logs.id` が `last_log_id` と一致する | 二重送信・戻るボタン（表示後に別の回答が記録されている） |

- 条件を満たさない場合は何も記録せず、結果 `Rejected`（理由付き）を返す。Controller は `review.show` へリダイレクトし、「この回答は記録しませんでした（理由）」を表示する
- `last_log_id` は、練習で不正解になった直後の二重送信を防ぐためのもの（再出題は正解するまで続くため、Bの条件だけでは2回目の送信も受け付けてしまう）。`scheduled` にも同じ判定を適用する
- `mode` を画面から受け取るのは、期限内の問題として出した画面への送信が、別の扱い（練習）として記録されることを防ぐため（例: 不正解の直後に戻るボタンで同じ画面から再送信すると、`scheduled` としては期限前なので拒否される。練習として記録されない）

### 7.3 記録

| mode | review_logs | review_states |
| --- | --- | --- |
| `scheduled` | `stage_before` / `stage_after` 付きで1行追加 | `ReviewScheduler::next()` の結果で `stage`、`next_review_on`、`last_reviewed_at` を更新 |
| `practice` | `stage_before` / `stage_after` はNULLで1行追加 | 変更しない |

- 選択式は `choice_key` が正解の選択肢の `key` と一致すれば `correct`、そうでなければ `incorrect`
- 戻り値: `Recorded(ReviewLog)` または `Rejected(reason)`。Controllerは選択式なら `review.answers.show`（正誤と解説）へ、想起カードなら `review.show` へリダイレクトする

## 8. 日付の境界

| 状況 | 扱い |
| --- | --- |
| 23:59に表示した問題を0:01に回答 | 回答時点の「今日」で判定する。期限が来ている問題ならそのまま記録される |
| 前日に不正解で、再出題せずに日をまたいだ | 再出題は行わない（Bは今日の回答だけが対象）。問題は段階1・期限が今日として、Aで出題される |
| 何日も休んだ | 期限切れの問題はすべてAに入る。上限は設けない |
