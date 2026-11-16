# SCR-03 レッスン テスト設計

作成日: 2026-11-16
ステータス: 確定（2026-11-16）
対象: [SCR-03](../screens/SCR-03_lesson.md)
テストファイル: `tests/Feature/Screens/LessonTest.php`

| ID | 分類 | 条件 | 期待結果 |
| --- | --- | --- | --- |
| SCR-03-T01 | 表示 | `GET /lessons/ch01-l01` | 事前の問いと選択肢、GETフォームの送信先が `lessons.read`。本文と「答えずに進む」リンクは表示されない |
| SCR-03-T02 | 異常 | 存在しないid / 引退したレッスン（全5段階のURL） | 404 |
| SCR-03-T03 | 表示 | `read?answer=`（正解の key） | 「正解」と解説、本文、対象バージョン、出典リンク（`rel="noopener noreferrer"`） |
| SCR-03-T04 | 表示 | `read?answer=`（不正解の key） | 「不正解」、あなたの答えと正解 |
| SCR-03-T05 | 異常 | `read`（answer なし）/ `read?answer=z` | `lessons.show` へリダイレクト（事前の問いは回答必須） |
| SCR-03-T06 | 安全 | 本文・問題文に `<script>` と `[x](javascript:alert(1))` を含む | `<script>` はエスケープされる。`javascript:` のリンクは出力されない |
| SCR-03-T07 | 表示 | `recall` | 想起カードの問いと `<details>` 内の答え。本文・出典・選択式の問題は表示されない |
| SCR-03-T08 | 表示 | `recall`（引退した想起カードあり） | 引退したカードは表示されない |
| SCR-03-T09 | 正常 | `POST complete`（未完了、問題5件） | `lessons.completed` へリダイレクト。完了画面に「5 問を復習に登録しました」 |
| SCR-03-T10 | 正常 | `POST complete` を2回 | 2回目もリダイレクト。完了画面に「すでに復習に登録されています」。DBは1回目と同じ |
| SCR-03-T11 | 異常 | CSRFトークンなしの `POST complete` | 419。DB変更なし |
| SCR-03-T12 | 表示 | 完了済みで `completed` を直接表示 | 件数の文はなく、ホームへのリンクと次のレッスン |
| SCR-03-T13 | 異常 | 未完了で `completed` を直接表示 | `lessons.show` へリダイレクト |
| SCR-03-T14 | 表示 | 事前の問いの選択肢を同じ日に2回表示 | 同じ順で表示される |
