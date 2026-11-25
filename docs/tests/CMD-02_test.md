# CMD-02 lessons:check-sources テスト設計

作成日: 2026-11-24
ステータス: 確定（2026-11-25）
対象: [CMD-02](../commands/CMD-02_lessons-check-sources.md)
テストファイル: `tests/Feature/Commands/CheckLessonSourcesCommandTest.php`

## 1. 方針

- `Http::preventStrayRequests()` と `Http::fake()` で、本物のサイトには通信しない
- `Sleep::fake()` で、取得の間隔と再試行の待ちを実際には待たない
- 教材は `tests/Fixtures/content/valid/` を使い、URL を変えたい場合は `ContentFixture`（CMD-01 のテストと同じ）で一時フォルダに書き換えて作る
- DB は使わない

## 2. テスト

| ID | 条件 | 期待結果 |
| --- | --- | --- |
| CMD-02-T01 | すべてのファイルが 200 で、アンカーもある | 終了コード0。ファイル・URL・対象外の件数が正しい |
| CMD-02-T02 | 教材の URL `https://laravel.com/docs/13.x/{ページ}#{アンカー}` / `references` の `https://laravel.com/docs/11.x/{ページ}` | それぞれ `https://raw.githubusercontent.com/laravel/docs/13.x/{ページ}.md`・`…/11.x/{ページ}.md` を取得する |
| CMD-02-T03 | 同じページの URL が、複数のレッスンに別のアンカーで書かれている | そのファイルは1回だけ取得する（`Http::assertSentCount`） |
| CMD-02-T04 | ファイルが 404 | 終了コード1。レッスンのファイル名・項目（`sources.0.url`）・「ページが見つかりません」が表示される。再試行しない |
| CMD-02-T05 | ファイルは 200 だが `<a name="{アンカー}">` がない | 終了コード1。「アンカーが見つかりません」が表示される |
| CMD-02-T06 | アンカーのない URL で、ファイルが 200 | エラーにならない |
| CMD-02-T07 | 503 が2回続いた後に 200 | 再試行して成功する。終了コード0 |
| CMD-02-T08 | 503 が続く / 接続の失敗が続く | 3回取得した後、警告を表示する。エラーがなければ終了コード0 |
| CMD-02-T09 | 2件以上のファイルを取得する | 取得の間に 1 秒の待ちが入る（`Sleep::assertSlept`） |
| CMD-02-T10 | `references` に `https://laravel.com/blog/...` がある | 取得しない。対象外の件数に数えられる |
| CMD-02-T11 | 教材に検証エラーがある | 終了コード1。CMD-01 と同じ形式でエラーを表示し、通信を1件もしない（`Http::assertNothingSent`） |
| CMD-02-T12 | 送るリクエスト | User-Agent が `laravel-learning-source-check` で、Authorization ヘッダーがない |
