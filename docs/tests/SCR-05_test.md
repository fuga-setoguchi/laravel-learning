# SCR-05 学習記録（SQL） テスト設計

作成日: 2026-11-27
ステータス: 確定（2026-11-27）
対象: [SCR-05](../screens/SCR-05_records.md)
テストファイル: `tests/Feature/Screens/RecordsTest.php`

テスト用DB（`testing`）に、読み取り専用のユーザーの接続（`pgsql_reader`）でつないで実行する。

テストの行は `RefreshDatabase` のトランザクションの中で作られ、確定しないため、別の接続である `pgsql_reader` からは見えない。そのため、テーブルの行を使わずに確かめられる SQL（`generate_series()`、`VALUES` など）を使う。テーブルを読めることは、権限（DB-T16）と、空のテーブルへのサンプルのクエリ（T17）で確かめる。

| ID | 分類 | 条件 | 期待結果 |
| --- | --- | --- | --- |
| SCR-05-T01 | 表示 | `GET /records`（`sql` なし） | 入力欄・実行ボタン・サンプルのクエリ・テーブルと列が表示される。結果は表示されない |
| SCR-05-T02 | 正常 | `SELECT 1 AS a, 'x' AS b` | 列名 `a`・`b` と、値 `1`・`x`。「1 行」 |
| SCR-05-T03 | 正常 | 0行になる SELECT | 列名が表示され、「0 行」 |
| SCR-05-T04 | 正常 | 同じ名前の列が2つある SELECT（`SELECT 1 AS id, 2 AS id`） | 2列とも表示される |
| SCR-05-T05 | 正常 | NULL・真偽値を返す SELECT | `NULL`、`true` / `false` と表示される |
| SCR-05-T06 | 正常 | 600行を返す SELECT（`generate_series(1, 600)`）と、1000万行を返す SELECT（`generate_series(1, 10000000)`） | どちらも 500 行だけ表示し、上限を超えた旨を表示する。1000万行でもメモリが尽きずに返る（DB 側で `LIMIT` が効く） |
| SCR-05-T07 | 正常 | 末尾に `;` がある SQL、末尾が `-- コメント` の SQL | どちらも実行できる |
| SCR-05-T08 | 正常 | 実行後の画面 | 入力欄に、実行した SQL が入っている |
| SCR-05-T09 | 守り | `INSERT` / `UPDATE` / `DELETE` / `TRUNCATE` / `DROP TABLE` / `CREATE TABLE` / `SET` / `COMMIT` / `DO` | エラー（包み方による構文エラー）を表示し、テーブルの行数・定義が変わらない |
| SCR-05-T10 | 守り | 複数の文（`SELECT 1; DELETE FROM review_logs`） | エラーを表示し、行数が変わらない |
| SCR-05-T11 | 守り | `SELECT pg_read_file('/etc/passwd')` | 権限のエラーを表示する |
| SCR-05-T12 | 守り | `SELECT current_user, rolsuper FROM pg_roles WHERE rolname = current_user` | `learning_reader`、`false`（スーパーユーザーでない） |
| SCR-05-T13 | 守り | 時間の上限を短くした設定で `SELECT pg_sleep(1)` | 時間の上限のエラーを表示する |
| SCR-05-T14 | 異常 | 4001文字の SQL | 実行せず、「SQL が長すぎます（4000 文字まで）。」 |
| SCR-05-T15 | 異常 | 構文の誤り（`SELEC 1`） | PostgreSQL のエラーの文言を表示する。接続先の情報（ホスト名・ユーザー名・パスワード）は表示しない |
| SCR-05-T16 | 表示 | `SELECT '<script>alert(1)</script>' AS x` | エスケープして表示する（`<script>` がそのまま出力されない） |
| SCR-05-T17 | 定義 | `SampleQueries::all()` のすべての SQL | エラーにならずに実行できる。画面の各見出しが `records?sql=` へのリンクになっている |
| SCR-05-T18 | 表示 | テーブルと列 | `review_logs` とその列（`answered_on` など）と短い型名（`date`）が表示され、`migrations` は表示されない。`review_logs` が `chapters` より前に並ぶ |
| SCR-05-T19 | 表示 | テーブルと列の参照先 | `review_logs.question_id` に `→ questions.id` が表示される |
| SCR-05-T20 | 表示 | テーブルと列の取りうる値 | `review_logs.result` に `ReviewResult` のすべての値が表示される |
| SCR-05-T21 | 表示 | `sql` を付けて実行した後の画面 | 結果と一緒に、テーブルと列も表示される |
| SCR-05-T22 | 守り（権限を直接確かめる） | 包み方を通さず、`pgsql_reader` の接続で直接実行する。読み取り専用のトランザクションの影響を除くため、先に `SET SESSION CHARACTERISTICS AS TRANSACTION READ WRITE` を実行する。`INSERT` / `UPDATE` / `DELETE` / `TRUNCATE` / `CREATE TABLE` / `DROP TABLE` / `COPY (SELECT 1) TO PROGRAM 'true'` / `SELECT pg_read_file('/etc/hostname')` | すべて権限のエラー（SQLSTATE `42501`）になり、テーブルの行数・定義が変わらない |
| SCR-05-T23 | 守り | `pgsql_reader` の設定を `app`（スーパーユーザー）に差し替えて実行する。また、接続先の DB を `postgres` に差し替えて実行する | 利用者の SQL を流さず、「読み取り専用の接続の設定が正しくないため、実行しませんでした。」を表示する |
| SCR-05-T24 | 異常 | 2行目に構文の誤りがある SQL | 行番号が1つ大きくなる旨の説明を添えて表示する |
| SCR-05-T25 | 表示 | テーブルと列のたたみ | テーブルごとに `<details>` があり、見出しにテーブル名と列の数（`review_logs（11）`）。学習記録の3テーブルは `open`、教材のテーブル（`chapters` など）は `open` なし |

読み取り専用のユーザーの作成は DB-T16・T17（[DB_test.md](DB_test.md)）、接続先の安全装置は ENV-T04（[README](README.md) 5章）で確かめる。
