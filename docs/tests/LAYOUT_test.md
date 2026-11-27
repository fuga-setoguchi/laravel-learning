# LAYOUT 共通レイアウト テスト設計

作成日: 2026-11-27
ステータス: 確定（2026-11-27）
対象: [LAYOUT](../screens/LAYOUT_common.md)
テストファイル: `tests/Feature/Screens/LayoutTest.php`

| ID | 分類 | 条件 | 期待結果 |
| --- | --- | --- | --- |
| LAYOUT-T01 | 表示 | `GET /`（ホーム） | メニューに「ホーム」「カリキュラム」「復習」「学習記録」がこの順で、それぞれ `home`・`curriculum`・`review.show`・`records` へのリンクとして表示される |
| LAYOUT-T02 | 表示 | ホーム・カリキュラム・レッスン・復習・学習記録の各画面 | 今いる画面の項目だけに `aria-current="page"` が付く（レッスンの画面では「カリキュラム」） |
| LAYOUT-T03 | 表示 | `GET /` | メニュー項目ごとにアイコン（`aria-hidden="true"` の SVG）が表示される |
| LAYOUT-T04 | 異常 | 一覧にないアイコン名で `Icon` を作る | `InvalidArgumentException` |
| LAYOUT-T05 | 定義 | `Sidebar::items()` のすべての項目 | ルート名が存在し、アイコン名が `Icon` の一覧にある |

ブラウザでの見た目（メニューの固定、狭い画面での横並び）は、自動テストでは確かめない（[README](README.md) 1章）。
