# CMD-02 lessons:check-sources

作成日: 2026-11-24
ステータス: 確定（2026-11-25）
対応要件: FR-14
関連: [04_content-format](../04_content-format.md) / [CMD-01](CMD-01_lessons-import.md) / [02_architecture](../02_architecture.md) 5章

## 1. 概要

| 項目 | 内容 |
| --- | --- |
| コマンド | `php artisan lessons:check-sources {--path=}` |
| クラス | `App\Console\Commands\CheckLessonSourcesCommand` |
| 処理の委譲先 | `ContentLoader` → `LessonFileValidator`（CMD-01 と同じ）→ `SourceLinkChecker` |
| 目的 | 教材の出典（`sources`）と補足資料（`references`）のうち、Laravel 公式ドキュメントの URL について、ページとアンカーが実在するかを機械的に確かめる |

| オプション | 既定 | 内容 |
| --- | --- | --- |
| `--path` | `config('learning.content_path')` | 教材フォルダ |

- DB は使わない（読み込みと検証は CMD-01 のクラスを使い、取り込みはしない）
- 確かめるのは「リンク先が存在するか」まで。教材の内容が出典の記載と合っているかは、これまでどおりレビューで確かめる

## 2. 処理の流れ

```
1. 読み込み・検証  CMD-01 と同じ。検証エラーがあれば、CMD-01 と同じ形式で表示して終了コード1（通信はしない）
2. URLの収集      全レッスンの sources と references から URL を集め、確認に読むファイルごとにまとめる
3. 確認          ファイルを1件ずつ取得し、ページの有無とアンカーの有無を判定する
4. 結果表示      エラー・警告・件数を表示する
```

## 3. 確認先

laravel.com のページではなく、公式ドキュメントの原稿がある GitHub のリポジトリ（`laravel/docs`）のファイルを、`raw.githubusercontent.com` から読む。

| 教材の URL | 確認に読むファイル |
| --- | --- |
| `https://laravel.com/docs/{バージョン}/{ページ}` または `…/{ページ}#{アンカー}` | `https://raw.githubusercontent.com/laravel/docs/{バージョン}/{ページ}.md` |

- `{バージョン}` は `13.x`・`11.x`・`master` など。リポジトリのブランチ名と同じ
- 確認先の先頭部分は `config('learning.docs_source_base')`（既定 `https://raw.githubusercontent.com/laravel/docs`）に置く
- ページの有無: ファイルが返れば（200）あり、404 ならなし
- アンカーの有無: ファイルの中に `<a name="{アンカー}">` があればあり。原稿では、見出しの前にこの形でアンカーが書かれている（7章）
- 上の形にあてはまらない URL（`references` の `https://laravel.com/blog/...`・`https://blog.laravel.com/...`）は確認の対象外とし、件数だけを表示する

laravel.com ではなく原稿を読む理由:

- laravel.com のサイトにアクセスしない（相手のサイトに負担をかけない）
- 原稿は Markdown なので、サイトの HTML の作りが変わっても、判定の仕方が変わらない
- `laravel/docs` は MIT License で公開されている

## 4. アクセスの仕方

相手のサービスに負担をかけないよう、次のとおりにする。

| 項目 | 内容 |
| --- | --- |
| 取得の単位 | 同じファイルは1回だけ取得する（同じページの別のアンカーは、取得したファイルで判定する）。2026-11-24 時点で、1回の実行あたり 45 ファイル |
| 順番と間隔 | 1件ずつ順に取得し、取得の間に 1 秒あける（並行して取得しない） |
| タイムアウト | 1件あたり 10 秒 |
| 再試行 | 接続の失敗・タイムアウト・429・5xx のときだけ、5 秒あけて 2 回まで再試行する。404 は再試行しない |
| User-Agent | `laravel-learning-source-check`（個人の情報を含めない） |
| 認証 | しない（GitHub のトークンを使わない） |
| 実行する場面 | CI だけ（6章）。毎日の起動などでは実行しない |

HTTP の通信は Laravel の HTTP クライアント（`Http` ファサード）で行う。テストでは `Http::fake()` で偽物にし、本物には通信しない（間隔の待ちも `Sleep::fake()` で省く）。

## 5. 判定と結果表示

| 結果 | 条件 | 扱い |
| --- | --- | --- |
| ページがない | 404 | エラー |
| アンカーがない | ファイルは返ったが、`<a name="{アンカー}">` がない | エラー |
| 確認できない | 再試行しても接続の失敗・タイムアウト・429・5xx。または 200・404 以外のステータス | 警告 |
| 対象外 | 3章の形にあてはまらない URL | 件数だけを表示 |

一時的な問題（相手のサービスの障害など）で CI を失敗させないため、「確認できない」は警告にとどめる。

### 表示形式

エラーがある場合:

```
ERROR  出典の確認で 2 件のエラーがありました。

  content/ch09/l02.md
    sources.2.url: ページが見つかりません（https://laravel.com/docs/13.x/queue）
  content/ch13/l02.md
    sources.5.url: アンカーが見つかりません（https://laravel.com/docs/13.x/queries#debugging）

WARN  確認できなかった URL が 1 件あります（一時的な問題の可能性があります）。
  https://laravel.com/docs/13.x/cache（HTTP 503）
```

エラーがない場合:

```
出典を確認しました。
  ファイル: 45 件 / URL: 364 件 / 対象外: 0 件
```

- 項目は CMD-01 と同じく、ドット区切りのパス（`sources.5.url`、0始まり）で示す
- 同じ URL が複数のレッスンにあれば、レッスンごとに表示する
- メッセージは `lang/ja/content.php` に定義する（CMD-01 と同じ）

## 6. CI での実行

既存の CI（`ci.yml`）とは分け、出典の確認だけのワークフロー（`.github/workflows/sources.yml`）にする。外部への通信の失敗が、lint やテストの結果と混ざらないようにするため。

| きっかけ | 条件 | 目的 |
| --- | --- | --- |
| `pull_request` | `content/**` を変えたとき。確認の仕組み（このワークフロー、`CheckLessonSourcesCommand`、`SourceLinkChecker`）を変えたときも | 教材に足した URL の誤りを、マージする前に見つける。仕組みを変えたときは、本物の公式ドキュメントで動くことを確かめる |
| `schedule` | 週1回（月曜 9:00 JST。cron は `0 0 * * 1`） | 公式ドキュメントの変更で、アンカーが消えたことを見つける |

- 手順: チェックアウト → PHP の準備 → `composer install` → `.env` の準備 → `php artisan lessons:check-sources`。DB は使わないので、PostgreSQL のサービスコンテナは置かない
- 権限は `contents: read` のみ。actions はコミットハッシュで固定する（`ci.yml` と同じ）
- 定期実行が失敗すると、GitHub から通知が届く（定期実行の通知は、ワークフローの cron を最後に変更した利用者に送られる）
- 公開リポジトリの定期実行は、リポジトリに 60 日間変更がないと、GitHub によって止められる。止まった場合は、Actions の画面から有効に戻す
- 手で実行するための Makefile のコマンドは作らない（必要なら `php artisan lessons:check-sources` を直接実行する）

## 7. 公式ドキュメントへのアクセスについて確認したこと

2026-11-24 に確認した。

| 確認したこと | 結果 |
| --- | --- |
| `https://laravel.com/robots.txt` | すべてのクローラーに対して禁止しているのは `/cdn-cgi/` だけで、`/docs/` は禁止していない。Crawl-delay の指定はない |
| laravel.com の利用規約 | `https://laravel.com/terms` は 404 で、自動のアクセスについての記載は見つからなかった |
| `laravel/docs` のライセンス | `license.md` に MIT License と書かれている |
| 原稿のアンカーの形式 | `https://raw.githubusercontent.com/laravel/docs/13.x/queues.md` に、見出しの前の `<a name="...">` が書かれている |
| 存在しないページ | `https://laravel.com/docs/13.x/this-page-does-not-exist` は 404 を返す |

確かめきれていないこと（実装した後、最初の実行の結果で確かめる）:

- laravel.com のページのアンカーが、原稿の `<a name>` と一致すること。既存の教材の URL の多くが「アンカーがない」となった場合は、確認先の方針（3章）を見直す
