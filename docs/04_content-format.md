# 教材フォーマット仕様書

作成日: 2026-11-16
ステータス: 確定（2026-11-16）
参照: [要件定義書](01_requirements.md) / [DB設計書](03_database.md) / [CMD-01](commands/CMD-01_lessons-import.md)

## 1. ファイル構成

```
content/
├── chapters.yaml        # 章の定義
├── ch01/
│   ├── l01.md           # レッスン（id: ch01-l01）
│   └── l02.md
└── ch02/
    └── l01.md
```

- 章のフォルダ名は `ch` + 2桁の章番号、レッスンのファイル名は `l` + 2桁の順番 + `.md`
- 文字コードはUTF-8、改行はLF
- `content/` 直下の `chapters.yaml` と `chNN/lNN.md` 以外のファイル（`README.md` など）は無視する

## 2. chapters.yaml

```yaml
chapters:
  - number: 1
    title: Laravelの全体像
  - number: 2
    title: ディレクトリ構成と設定
```

| 項目 | 必須 | 規則 |
| --- | --- | --- |
| `chapters` | ○ | 1件以上の配列 |
| `number` | ○ | 1〜99の整数。重複不可 |
| `title` | ○ | 1〜100文字 |

レッスンがまだない章を書いてもよい（カリキュラム画面に「準備中」と表示する）。`chapters.yaml` から消した章は引退させ、カリキュラムに表示しない（レッスンと同じく削除はしない）。その章を指すレッスンファイルが残っている場合は検証エラー。

## 3. レッスンファイル

### 3.1 全体

```markdown
---
id: ch03-l02
chapter: 3
order: 2
title: なぜコントローラーでnewせずに注入するのか
laravel_version: "13.x"
sources:
  - title: Service Container
    url: https://laravel.com/docs/13.x/container
pre_question:
  prompt: コントローラーの中で `new PaymentService()` と書くと、テストで困ることはどれか。
  choices:
    - key: a
      body: 処理が遅くなる
    - key: b
      body: テストで偽物の PaymentService に差し替えられない
    - key: c
      body: 文法エラーになる
  answer: b
  explanation: クラスの中で生成すると、外から差し替える手段がなくなります。
recall_cards:
  - id: ch03-l02-c1
    prompt: コンストラクタで依存を受け取ると、テストで何ができるようになるか。
    answer: 本物の代わりにモックや偽物を渡して、外部への通信などを避けて検証できる。
questions:
  - id: ch03-l02-q1
    type: choice
    prompt: サービスコンテナがコンストラクタの引数を解決するとき、何を手がかりにするか。
    choices:
      - key: a
        body: 引数の型宣言
      - key: b
        body: 引数の変数名
      - key: c
        body: PHPDocのコメント
    answer: a
    explanation: コンテナはリフレクションで型宣言を読み取り、その型のインスタンスを作って渡します。
  - id: ch03-l02-q2
    type: code_review
    prompt: AIが書いた次のコントローラーの問題点として、最も適切なものはどれか。
    code: |
      public function store(Request $request)
      {
          $service = new PaymentService(new StripeClient(config('services.stripe.key')));
          $service->charge($request->input('amount'));
      }
    choices:
      - key: a
        body: 依存を内部で生成しているため、テストで差し替えられず、設定の組み立てもコントローラーが担っている
      - key: b
        body: メソッド名が store であることが規約違反である
      - key: c
        body: config() をコントローラーで呼んではいけない
    answer: a
    explanation: |
      PaymentService はコンストラクタやメソッドの引数で受け取り、StripeClient の組み立てはサービスプロバイダに任せます。
---

## 概念

（本文。Markdown）
```

- 先頭の `---` から次の `---` までをYAMLのfront matter、それ以降を本文とする
- 文字列の項目（`prompt`、`body`、`answer`、`explanation`）と本文はMarkdownとして表示する
- `laravel_version` は `"13.x"` のように引用符を付けて書く（推奨）。検証は「解析後の値が文字列で、`^\d+\.x$` に一致すること」で行う（引用符の有無そのものは検証できない）

### 3.2 項目の規則

#### レッスン

| 項目 | 必須 | 規則 |
| --- | --- | --- |
| `id` | ○ | `^ch\d{2}-l\d{2}$`。ファイルの位置（`chNN/lNN.md`）と一致すること |
| `chapter` | ○ | 整数。`id` の章番号と一致し、`chapters.yaml` に存在すること |
| `order` | ○ | 整数。`id` のレッスン番号と一致すること |
| `title` | ○ | 1〜100文字 |
| `laravel_version` | ○ | 文字列。`^\d+\.x$` |
| `sources` | ○ | 1件以上の配列 |
| `pre_question` | ○ | 下記「事前の問い」 |
| `recall_cards` | ○ | 1件以上の配列（推奨3〜5件） |
| `questions` | ○ | 1件以上の配列（推奨1〜2件） |
| 本文 | ○ | 空でないこと |

#### 出典（sources の各要素）

| 項目 | 必須 | 規則 |
| --- | --- | --- |
| `title` | ○ | 1〜100文字 |
| `url` | ○ | `https://laravel.com/docs/{laravel_version}/` で始まる（例: `13.x` なら `https://laravel.com/docs/13.x/`）。その後にページ名が1文字以上ある。`#` 以降のアンカーは可 |

#### 事前の問い（pre_question）

| 項目 | 必須 | 規則 |
| --- | --- | --- |
| `prompt` | ○ | 1文字以上 |
| `choices` | ○ | 2〜6件。各要素は `key`（`a`〜`f`、重複不可）と `body`（1文字以上） |
| `answer` | ○ | `choices` のいずれかの `key` |
| `explanation` | ○ | 1文字以上 |

#### 想起カード（recall_cards の各要素）

| 項目 | 必須 | 規則 |
| --- | --- | --- |
| `id` | ○ | `{レッスンid}-c{1以上の整数}`（例: `ch03-l02-c1`） |
| `prompt` | ○ | 1文字以上 |
| `answer` | ○ | 1文字以上 |

#### 問題（questions の各要素）

| 項目 | 必須 | 規則 |
| --- | --- | --- |
| `id` | ○ | `{レッスンid}-q{1以上の整数}`（例: `ch03-l02-q1`） |
| `type` | ○ | `choice` または `code_review` |
| `prompt` | ○ | 1文字以上 |
| `code` | `code_review` のみ必須 | `choice` では書かない |
| `code_language` | × | 既定 `php`。`php` / `blade` / `bash` / `json` / `yaml` / `sql` のいずれか。`blade` は highlight.js の `php-template` として表示する（実装時に同梱ファイルに含まれるか確認する） |
| `choices` | ○ | 2〜6件。規則は事前の問いと同じ |
| `answer` | ○ | `choices` のいずれかの `key`（正解は1つ） |
| `explanation` | ○ | 1文字以上 |

### 3.3 ファイルをまたぐ規則

想起カード（`-cN`）と問題（`-qN`）はidの接頭辞で区別されるため、同じidのまま想起カード ⇔ 選択式に種別が変わることはない（`choice` ⇔ `code_review` の変更は可）。

レッスンの `id`・`chapter`・`order` はファイルの位置と一致させるため、レッスンの `id` と章内の `order` は自動的に一意になる。それ以外に、次を検証する。

| 規則 | 理由 |
| --- | --- |
| `chapters.yaml` の `number` は一意 | 章が決まらない |

問題の `id`（想起カードを含む）は、レッスンの `id` を接頭辞に持つため、ファイルをまたいで重複することはない。同じファイルの中での重複は検証する（学習記録の対応が壊れるため）。

## 4. idの運用ルール

- 一度取り込んだ問題の `id` は変えない。内容を直すときは `id` を保ったまま書き換える
- 問題の意味が大きく変わる場合（別の知識を問うようにする場合）は、古い問題を削除し、新しい番号の `id` を付ける。削除した問題の番号は再利用しない
- 問題の並び順は `id` の番号ではなく、ファイル内の記載順で決まる
- 削除したレッスンの `id`（章・順番）は再利用しない。再利用すると、古い問題の `id`（`-c1` など）が復活し、内容の違う問題に以前の段階が引き継がれてしまう
- 章の途中にレッスンを挿入することはできない（後ろのレッスンの `id` がすべて変わり、学習記録が引き継がれないため）。追加は章の末尾に行う
- レッスンを別の章・順番に移す場合は、新しい `id` のレッスンとして作り、古いファイルを削除する（問題の `id` も新しくなり、学習記録は引き継がれない）

## 5. 書き方の注意

- 公式ドキュメントの文章を転載しない。自分の言葉で書く
- 本文中のコードは言語付きのフェンス（```` ```php ````）で書く
- HTMLタグは書かない（表示時にエスケープされ、文字として表示される）
- 教材の書き方の詳細は [教材生成ガイド](06_content-generation-guide.md) に従う
