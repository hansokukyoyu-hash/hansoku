# サイト仕様書（農機具・工具買取サイト）

> **変更前に必ずこのファイルを読むこと。** ここに書かれた slug・フィールド名・設定キー・依存関係は、本番データ（投稿・メタ・設定）と結びついています。名前を変えると表示が壊れたりデータが見えなくなったりします。
> 最終更新：2026-10-05（テーマ 1.1.0）

---

## 1. 本番環境

| 項目 | 値 |
|---|---|
| URL | https://tenpos.online/kaitori/ （WordPress はサブディレクトリ `/kaitori/` にインストール） |
| WordPress | 7.1.2 |
| PHP | 8.4 |
| サーバー | お名前.com レンタルサーバー（Web: 160.251.148.241 / `www1111.onamae.ne.jp`） |
| テーマ | `tenpos-kaitori`（本リポジトリ `wp-content/themes/tenpos-kaitori/`）バージョン 1.1.0（本番は 1.0.1。1.1.0 は反映待ち） |
| 必須プラグイン | Advanced Custom Fields（無料版）、Contact Form 7 |
| その他プラグイン | WP Mail SMTP（お名前メールの SMTP で送信）、Flamingo（CF7 送信内容の保存） |
| パーマリンク | 投稿名（`/%postname%/`） |
| 運営会社 | 株式会社テンポスバスターズ（東証上場グループ） |

### メール（2026-10-02 時点）
- 送信元：`[_site_title] <kaitori@tenpos.online>`（CF7）／WP Mail SMTP 経由
- SPF：PASS（`v=spf1 include:_spf.onamae.ne.jp ~all`、送信 IP 160.251.132.144）
- DKIM：PASS（`d=tenpos.online`、`default._domainkey` 登録済み）
- DMARC：**FAIL（`_dmarc.tenpos.online` 未登録）** → 追加予定（docs/TODO.md）
- MX：`mail1026.onamae.ne.jp`

---

## 2. 構成の原則

- **クラシックテーマ（PHP テンプレート）**。ブロックテーマではない。`theme.json` なし。
- 依存ライブラリなし。CSS 1 本（`assets/css/main.css`）、JS 1 本（`assets/js/main.js`、defer）。
- ACF の入力項目は **コード（`inc/fields.php`）で定義**。ACF 管理画面でフィールドグループを作らない（重複・不整合の原因）。
- ACF が無くても動くよう、値の取得は必ず `tk_field()`（ACF → post meta / term meta の順）を使う。
- 電話番号・会社情報・CF7 のフォーム ID は **カスタマイザー（theme_mod）** に保存。テンプレートに直書きしない（`tk_opt()` で取得）。
- デザインの元は `mock/`（静的 HTML モック）。テーマの CSS/JS はモックから派生し、WordPress 用の追記をしている。**モックとテーマは自動同期しない**。

---

## 3. URL・テンプレート対応表

| URL | 内容 | テンプレート | 備考 |
|---|---|---|---|
| `/` | トップ（ハブ） | `front-page.php` | 固定ページ slug `top` をフロントページに設定 |
| `/agricultural-equipment/` | 農機具 LP | `page-agricultural-equipment.php` | **slug で自動適用**。slug 変更禁止 |
| `/tool/` | 工具 LP | `page-tool.php` | **slug で自動適用**。slug 変更禁止 |
| `/results/` | 買取実績（全体） | `archive-result.php` | |
| `/agricultural-equipment/results/` | 農機具の実績 | `archive-result.php`（`taxonomy-genre.php` 経由） | 独自リライトルール |
| `/tool/results/` | 工具の実績 | 同上 | 独自リライトルール |
| `/results/{slug}/` | 実績詳細 | `single-result.php` | |
| `/column/` | コラム一覧（連載＋新着） | `archive-column.php` | |
| `/column/series/{slug}/` | 連載トップ | `taxonomy-series.php` | |
| `/column/{slug}/` | コラム記事 | `single-column.php` | |
| `/glossary/` | 用語集 | `archive-glossary.php` | `/glossary/{slug}/` は一覧の `#slug` へ 301 |
| `/faq/` | よくある質問 | `archive-faq.php` | |
| `/contact/` | お問い合わせ | `page.php` | slug `contact` のときだけ CF7（`tk_cf7_contact`）を表示 |
| `/area/` `/terms/` `/privacy-policy/` | 固定ページ | `page.php` | |
| その他 | | `index.php` `single.php` `404.php` | |

### 変更禁止の slug（テンプレート・リンクが依存）
固定ページ：`top` `agricultural-equipment` `tool` `contact` `area` `terms` `privacy-policy`
ジャンル（genre）：`agri` `tool` `deal`

---

## 4. データモデル

### カスタム投稿タイプ（`inc/post-types.php`）
| post_type | 名称 | アーカイブ | supports |
|---|---|---|---|
| `result` | 買取実績 | `/results/` | title, editor, thumbnail, excerpt |
| `column` | コラム | `/column/` | title, editor, thumbnail, excerpt, revisions, author |
| `glossary` | 用語集 | `/glossary/` | title, editor |
| `faq` | よくある質問 | `/faq/` | title, editor, page-attributes（並び順＝menu_order） |

### タクソノミー
| taxonomy | 対象 | slug | 用途 |
|---|---|---|---|
| `genre` | result, column, glossary, faq | `/genre/` | 農機具 `agri`／工具 `tool`／買取・手続き `deal`。ページのトーン（配色）判定にも使用 |
| `series` | column | `/column/series/` | 連載 |

> **重要：タクソノミーは投稿タイプより先に登録している。** 逆にすると `/column/series/{slug}/` が `/column/{post}/` のルールに吸われて 404/誤リダイレクトになる（過去に発生）。

### フィールド（ACF キー名＝post meta キー名。**変更禁止**）
| 対象 | name | 内容 |
|---|---|---|
| result | `rs_maker` | メーカー |
| result | `rs_model` | 型式・型番 |
| result | `rs_year` | 年式 |
| result | `rs_condition` | 状態：`ok`稼働 / `ng`不動 / `broken`故障 / `S` `A` `B` `C`ランク |
| result | `rs_area` | 買取地域（都道府県） |
| result | `rs_price` | 買取価格（円・数値） |
| column | `col_episode` | 連載の回数（数値） |
| column | `col_read_min` | 読了目安（分） |
| column | `col_conclusion` | この記事の結論（1文） |
| column | `col_points` | 結論のポイント（1行1つ） |
| series（term） | `series_total` | 全何回 |
| series（term） | `series_genre` | `agri` / `tool` / `deal` |
| series（term） | `series_lead` | リード文 |
| series（term） | `series_schedule` | 更新ペース |
| glossary | `gl_yomi` | よみ（ひらがな・必須。五十音の行と並び順に使用。未入力だと一覧に出ない） |
| glossary | `gl_related` | 関連用語（post ID 配列） |
| glossary | `gl_link` | 詳しく読むリンク（ACF link 配列：url, title, target） |

ACF フィールドキー（`field_tk_*`）・グループキー（`group_tk_*`）も変更禁止。

### カスタマイザー（外観 → カスタマイズ → 買取サイト設定）
| キー | 内容 | 現在の本番値（2026-10-02） |
|---|---|---|
| `tk_tel` | 電話番号 | **仮 `0120-000-000`** |
| `tk_tel_hours` | 受付時間 | 受付 8:00〜20:00／年中無休 |
| `tk_company` | 運営会社表記 | 既定値 |
| `tk_license` | 古物商許可番号 | **仮 `第000000000000号`** |
| `tk_cf7_agri` | CF7 フォームID（農機具） | 設定済み |
| `tk_cf7_tool` | CF7 フォームID（工具） | 設定済み |
| `tk_cf7_contact` | CF7 フォームID（共通） | **未設定**（/contact/ は「準備中」表示） |

### カスタマイザー（外観 → カスタマイズ → 農機具LP：アフリカ訴求）※ 1.1.0 で追加
| キー | 内容 | 既定値 |
|---|---|---|
| `tk_africa_enable` | 表示オン／オフ（`'1'` / `''`） | `'1'`（表示） |
| `tk_africa_badge` | ファーストビューの帯の文言（空なら帯を出さない） | 買い取った農機具は、アフリカの農業で再び活躍します |
| `tk_africa_title` | セクション見出し（改行可） | あなたの農機具を、⏎アフリカの農業の力に。 |
| `tk_africa_lead` | セクション本文（結論の一文） | テンポスが買い取った農機具は、整備したうえでアフリカへ輸出され、… |
| `tk_africa_image` | セクションの写真（添付ファイル ID）。未設定時は「日本→アフリカ」の図 | なし |

既定値は `inc/helpers.php` の `tk_africa_defaults()` が唯一の定義元（カスタマイザーの default と `tk_opt()` の両方がここを参照）。

### オプション・その他の保存データ
- `wp_page_for_privacy_policy`：プライバシーポリシーページ（テーマの初期設定で設定）
- プライバシーポリシーページの post meta `_tk_privacy_template`：テーマのひな形で作成した印（これがあると初期設定で上書きしない）
- ブラウザの localStorage `tempos-column-read`：連載の既読（閲覧者の端末のみ）

---

## 5. 機能の仕組み（壊しやすい箇所）

### フローティング Dock・目次（`parts/dock.php` + `main.js`）
- 画面下のカプセル（電話／メニュー／WEB査定）。メニューは `#sheet` を円形に展開。
- **目次・チップ・読了リングは、本文中の `[data-nav]` 要素から JS が自動生成**。
- **ヘッダー直下の目次タブ（`parts/anchor-tabs.php`）は、ページ内の `[data-nav]` セクションと「同じ順番・同じ数」であること**（JS がインデックスで対応付け）。LP にセクションを追加・削除したらタブ配列も直す。
- ページごとの Dock 設定は、テンプレート冒頭の `tk_dock_config([...])`。

### 農機具 LP のセクション構成（1.1.0）
| 順 | id | data-nav | 部品 | 備考 |
|---|---|---|---|---|
| FV | — | — | テンプレート内 | 帯 `.africa-badge`（`#africa` へのリンク）を見出しの上に表示 |
| 1 | `africa` | アフリカで再活用 | `parts/africa.php` | `tk_africa_enabled()` が偽なら帯・セクション・目次タブをまとめて出さない |
| 2 | `reason` | 買取できる理由 | テンプレート内 | |
| 3 | `items` | 対応品目 | テンプレート内 | |
| 4 | `results` | 買取事例 | `parts/results-latest.php` | |
| 5 | `flow` | 引き取りの流れ | テンプレート内 | |
| 6 | `faq` | よくある質問 | `parts/faq-list.php` | |
| 7 | `form` | 無料査定 | `parts/estimate.php` | |

目次タブ（`page-agricultural-equipment.php` 冒頭の items 配列）もこの順番。セクションを増減するときは両方を直す。

**アフリカ訴求の事実関係（2026-10-05 ユーザー確認）**：買い取った農機具をアフリカへ輸出し、現地で再活用されている。国名・提携先・台数・現地写真など具体的な情報は**掲載可能なものなし**。→ 文言は事実の範囲にとどめ、数字・国名を書かない。追加する場合は根拠資料を確認のうえカスタマイザーで編集。

### トーン（配色）
- `tk_theme_tone()` が `hub` / `agri` / `tool` を返し、`body` に `theme-*` クラスを付与。CSS 変数がこれで切り替わる。
- 判定：固定ページの最上位 slug、genre タクソノミー、投稿の genre → 連載の `series_genre`。

### コラム本文の自動処理（`tk_prepare_column_content()`）
- 本文の **H2** に id・`data-nav`・「SECTION 01」ラベルを付与し、目次を生成。
- **H2 直後の段落は「結論の要約」枠**で表示（CSS `.prose h2 + p`）。
- H2 が 4 つ以上あると、3 つ目の H2 の前に査定 CTA を自動挿入。
- 連載：**予約投稿（未来日付）した回は、路線図・次回欄に「公開予定日」として表示**（`post_status` future を取得）。

### 用語集
- `[term slug="..."]表示名[/term]` で本文に用語ポップアップ（`inc/shortcodes.php`）。
- 並び順は `gl_yomi` の meta_value 順。行の振り分けは `tk_kana_row()`。

### フォーム（Contact Form 7）
- **テーマは CF7 の CSS を読み込まない**（`wpcf7_load_css` false）。代わりに `main.css` 末尾でスタイル。CF7 の更新で新しい要素が出た場合はテーマ側に CSS を足す（例：1.0.1 で `.hidden-fields-container` を非表示に追加）。
- CF7 の JS はフォームを表示するページだけ読み込む（`wpcf7_enqueue_scripts()`）。
- フォーム本文は `docs/cf7-agri.txt` / `docs/cf7-tool.txt` のひな形。テーマの CSS はこのマークアップ（`.form-grid` `.field` `.req` `.hint`）前提。
- フォーム ID 未設定時は電話導線を表示（管理者にのみ設定案内）。
- 送信内容は Flamingo に保存される（個人情報。管理者アカウントは最小限に）。

### 構造化データ（`inc/schema.php`）
トップ：Organization／LP・/faq/：FAQPage（FAQ 投稿から生成）／実績詳細：Product+Offer／記事：Article（連載は CreativeWorkSeries）+BreadcrumbList／用語集：DefinedTermSet。
※ FAQ を LP 内に手書きで追加しない（構造化データと不一致になる）。FAQ は投稿タイプ `faq` で管理。

### 管理画面「外観 → 買取サイト初期設定」（`inc/admin-setup.php`）
- 不足している固定ページ・ジャンルを作成、トップをフロントページに設定。**既存のものは作らない／変更しない**。
- プライバシーポリシー：未公開かつ WordPress 標準の未編集の下書きのときだけ、テーマのひな形で**下書き**を作成。公開済み・編集済みは触らない。
- 「サンプルコンテンツも投入する」：同じ slug があればスキップ（重複しない）。**本番では既に投入済み**。

---

## 6. ファイル構成（テーマ）

```
style.css            テーマヘッダー（Version を上げること）
functions.php        inc/*.php を読み込むだけ
inc/setup.php        theme support、body_class、pre_get_posts、用語個別→一覧リダイレクト
inc/helpers.php      tk_field / tk_opt / tk_theme_tone / 連載・路線図 / パンくず / tk_prepare_column_content
inc/post-types.php   CPT・タクソノミー・リライトルール・管理画面の列
inc/fields.php       ACF ローカルフィールド定義
inc/customizer.php   買取サイト設定
inc/enqueue.php      CSS/JS・フォント・不要 CSS の除去
inc/forms.php        CF7 表示・読み込み制御
inc/shortcodes.php   [term]
inc/schema.php       JSON-LD
inc/admin-setup.php  初期設定画面
inc/privacy.php      プライバシーポリシーのひな形
inc/sample-data.*    サンプルデータ（JSON）と投入処理
parts/               dock / brand / icons / estimate / faq-list / result-card / results-latest / post-card / anchor-tabs
docs/                SETUP.md（導入手順）、CF7 ひな形、privacy-policy.txt
```

---

## 7. 既知の注意点（過去に起きたこと）

| 事象 | 原因 | 対策（実装済み） |
|---|---|---|
| `/column/series/{slug}/` が 404・別記事へリダイレクト | タクソノミー登録が CPT より後 | タクソノミーを先に登録 |
| フォーム上部に空の枠 | CF7 CSS 非読込で hidden-fields の fieldset が見えた | `.wpcf7 .hidden-fields-container{display:none}` |
| `/privacy-policy/` が 404 | WP 標準の下書きを「作成済み」と誤判定 | 初期設定で下書きを検出・ひな形作成。フッターは公開済みのみリンク |
| 実績 URL が日本語エンコード | slug を「メーカー名-型式」で生成 | slug は `{genre}-{model}` |
| ローカル検証環境の siteurl が `/www/kaitori` に化ける | php ビルトインサーバー＋router の初回アクセス | `wp option update home/siteurl` で修正（tools/ のスクリプトで対処済み） |
