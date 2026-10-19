# 引き継ぎ書（農機具・工具買取サイト tenpos.online/kaitori）

> 作成：2026-10-19（テーマ 1.5.3 時点）。別の Claude Code アカウント・担当者が作業を引き継ぐための入口です。
> **このファイル → `CLAUDE.md` → `docs/SPEC.md` → `docs/WORKLOG.md` → `docs/TODO.md` の順に読んでから作業してください。**

---

## 1. サイトの概要
| 項目 | 内容 |
|---|---|
| サイト | 農機具・工具の買取サイト https://tenpos.online/kaitori/ |
| 運営 | 株式会社テンポスバスターズ（表記は確認中。`docs/FACTCHECK.md`） |
| 目的 | 農機具（農家・離農・遺品整理）と工具（職人・工務店）の買取の問い合わせ獲得 |
| 主なページ | トップ（2 つの入口）／農機具 LP `/agricultural-equipment/`／工具 LP `/tool/`／買取実績 `/results/`／コラム（連載）`/column/`／用語集 `/glossary/`／FAQ `/faq/`／お問い合わせ `/contact/`／プライバシーポリシー・利用規約・対応エリア |
| 成果（目標） | フォーム送信完了（GA4 キーイベント `contact`） |
| 仕組み | WordPress 7.1.2 ＋ オリジナルクラシックテーマ `tenpos-kaitori`（本リポジトリで管理） |

## 2. 本番環境
| 項目 | 内容 |
|---|---|
| サーバー | お名前.com レンタルサーバー（nginx キャッシュ・WAF あり）。PHP 8.4 |
| WordPress | 7.1.2、`/kaitori/` サブディレクトリ、パーマリンクは投稿名 |
| テーマ | `tenpos-kaitori` **1.5.3**（2026-10-15 反映・確認済み） |
| プラグイン | Advanced Custom Fields（無料版）、Contact Form 7、Flamingo、WP Mail SMTP、Site Kit by Google |
| 本番の変更方法 | **ユーザーが管理画面から行う**（テーマ ZIP のアップロード、カスタマイザー、投稿、CF7）。Claude は閲覧（GET）で確認するだけ |

サーバー固有の注意（詳細は SPEC.md §7）
- **WAF**：カスタマイザーで URL を含む値を保存すると、「何かうまくいかなかったようです」のエラーになることがある（2026-10-05）
- **REST API の国外アクセス制限**：海外の IP からの `/wp-json/wp/v2/` は 403 になる。Claude の作業環境は海外の IP なので、本番の投稿を REST で読み書きできない（`?rest_route=` の経路は通るが、使うにはユーザーの了承が必要）
- **ページキャッシュ（nginx）**：確認するときは `?nc=<乱数>` を付けると最新の状態を見られる
- ルートの `robots.txt` は他のサイト（/sougi-navi/ ほか）と共用。kaitori のサイトマップは載っていない

## 3. リポジトリ
| 項目 | 内容 |
|---|---|
| GitHub | `hansokukyoyu-hash/hansoku` |
| 正のブランチ | **`main`**（2026-10-19 に [PR #1](https://github.com/hansokukyoyu-hash/hansoku/pull/1) で、それまでの作業ブランチ `claude/wordpress-site-setup-4fni0c` をマージ済み） |
| 作業ブランチ | セッションごとに作られるブランチで作業し、main へはプルリクエストで取り込む（ユーザーがマージ）。旧ブランチ `claude/wordpress-site-setup-4fni0c` はリリース履歴の参照元なので削除しない |
| 構成 | `wp-content/themes/tenpos-kaitori/`（本番テーマ・正）／`docs/`（仕様・履歴・TODO・精査リスト）／`tools/`（ローカル再現環境・確認スクリプト）／`mock/`（デザインの元の静的モック） |
| 版の管理 | タグは使わず、コミット ID で管理（`docs/WORKLOG.md` のリリース履歴）。戻すときはその ID から ZIP を作る（CLAUDE.md） |

## 4. 新しいアカウントで作業を始める準備
1. **GitHub**：新しいアカウントの Claude Code から `hansokukyoyu-hash/hansoku` にアクセスできるようにする（GitHub の接続と、Claude の GitHub App をリポジトリに入れる）
2. **クラウド環境のネットワーク**：次のホストへのアクセスを許可する
   - `tenpos.online`（本番の閲覧確認）
   - `wordpress.org`・`downloads.wordpress.org`（ローカル再現環境の WordPress・プラグインの取得）
   - `raw.githubusercontent.com`（WP-CLI の取得）
   - npm のレジストリ（`tools/` の Playwright のインストール）
3. **最初のセッション**で main を開き、次のように依頼する：
   > 「docs/HANDOVER.md と CLAUDE.md を読んで、ローカル再現環境を作り、本番の閲覧確認（tools/check-live.sh と tools/check-pages.mjs）を実行して、現状を報告してください」
4. ローカル再現環境の作り方・合格基準は `CLAUDE.md`「変更の手順」4 のとおり（WP 7.1.2＋ACF＋CF7＋SQLite、`php -S localhost:8090`）
5. 通常の作業では、環境変数・秘密情報は**不要**

## 5. 作業ルール（要点。正は CLAUDE.md）
- 本番に直接変更を加えない。本番への反映はユーザーが行う。パスワードを受け取らない・求めない
- 本番でフォーム送信など副作用のある操作をしない（テスト送信はユーザーに依頼）
- slug・post_type・taxonomy・ACF の name/key・カスタマイザーのキー・テーマのディレクトリ名は**変更禁止**（本番データと切れる）
- 不具合は推測で直さず、SPEC を読んでローカルで再現してから直す
- 変更したら：php -l → ローカル検証（check-pages「OK」・debug.log 0 行・スクリーンショット目視）→ バージョンを上げる → SPEC/WORKLOG を更新 → コミット・プッシュ → リリース履歴に記録 → ZIP をユーザーに渡す → 反映後に本番を閲覧確認
- **料金表示のルール**（SPEC §3.5）：査定料・キャンセル料は無料／**出張費は有料なので触れない**／搬出（レッカー）・名義変更の代行は有料（触れるときは「（有料）」）。景品表示法の有利誤認を避けるため必ず守る
- REST で本番の投稿を書き換える例外（2026-10-15、12 件）は**実施済み・終了**。再び必要なら改めてユーザーの了承を得る

## 6. 外部サービス・連携
| 連携 | 設定場所 | 内容 |
|---|---|---|
| フォーム（CF7） | 本番の CF7 管理画面（**テーマの外**） | 農機具査定 ID 62／工具査定 63／総合問い合わせ 80。カスタマイザーには英数字の ID が入っていても動く（1.5.1〜）。ひな形は `wp-content/themes/tenpos-kaitori/docs/cf7-*.txt`。メールアドレスは 3 フォームとも必須（2026-10-05） |
| 送信内容の保存 | Flamingo | 問い合わせはすべて Flamingo とメールに残る |
| メール送信 | WP Mail SMTP（お名前メール） | 送信元 `kaitori@tenpos.online`。SPF・DKIM は PASS、**DMARC は未登録** |
| アクセス解析 | Site Kit（Google タグ `GT-PZVL6ZGK`） | 送信成功で GA4 イベント `contact`（キーイベント登録済み）。ログイン中は計測されない。テーマで GA タグを足さない（二重計測になる） |
| スプレッドシート | Google Apps Script（ユーザーの Google アカウント内） | Apps Script が **5 分ごと**に `POST /wp-json/tk/v1/sheets/pull` で取りに来て追記し、`ack` でサイト側の一時保管を消す方式（1.5.0〜）。合言葉（トークン）はカスタマイザー「スプレッドシート連携」と Script Properties の `TOKEN` に保存。コードと手順は `wp-content/themes/tenpos-kaitori/docs/gas/`。2026-10-05 にシートへの反映を確認済み |

## 7. 秘密情報の扱い
- リポジトリに秘密情報は入っていない。チャットに書かない・書かせない
- スプレッドシートのトークンは、WordPress のカスタマイザーと Apps Script の Script Properties にだけある。漏れた疑いがあれば Apps Script の `resetToken` を実行し、新しい値をカスタマイザーに入れる
- 2026-10-15 に REST 用のアプリケーションパスワードを使った。**ユーザーが削除済みか要確認**（TODO）

## 8. 現在の状態と未完了の作業（2026-10-19）
完了：テーマ化・本番導入、アフリカ訴求、Site Kit・GA4 目標、総合問い合わせ、送信完了の表示、スプレッドシート連携、メールアドレス必須化、料金表示の修正（テーマ・用語集・FAQ・コラム・サイトのタイトル）

**至急**
- **本文のないコラムが公開中**（2026-10-19 確認）：`/column/rinou-03/`・`/column/tool-hanashi-01/`・`/column/tool-hanashi-02/` が「（本文は公開日までに作成します）」のまま。さらに予約投稿が **10/22**（rinou-04）・10/29（tool-hanashi-03）・11/5（rinou-05）・11/12（tool-hanashi-04）に自動公開される。本文を書くか、下書きに戻す（ユーザー）

**ユーザーの確認待ち**
- 掲載内容の精査（`docs/FACTCHECK.md`。45 項目の精査リストと、会社・連絡先情報 18 項目）：仮の電話番号 `0120-000-000`、受付時間、「40年以上」「1,284台」「¥25,000」、架空の買取実績 8 件、工具の店頭・宅配買取の実態、アフリカ訴求の「整備」、「全国対応」など
- 未完成のページ：対応エリア・店舗（`/area/`、仮の文）、利用規約（`/terms/`、本文なし）、WordPress 初期の「Hello world!」「Sample Page」が公開中
- CF7 のメールタブ（`Reply-To: [your-email]`）・3 フォームのテスト送信、旧ウェブアプリのデプロイのアーカイブ、DMARC の登録

**テーマ側でできること（GEO 対策 B。着手可）**
- サイトマップの整理（`/author/kaitori/`・未分類カテゴリ・用語と FAQ の個別 URL を外す）
- meta description・OGP・X カード
- 構造化データの拡充（Organization の住所・電話・古物商、Service、記事の dateModified）。正式な会社情報がそろってから
- 精査結果を受けた文言の修正

詳細・チェックリストは `docs/TODO.md`。

## 9. 主なドキュメント
| ファイル | 内容 |
|---|---|
| `CLAUDE.md` | 作業ルール（必読） |
| `docs/SPEC.md` | 仕様書：URL とテンプレート、データモデル、カスタマイザーのキー、壊しやすい箇所、料金表示のルール、既知の注意点 |
| `docs/WORKLOG.md` | 作業履歴・本番反映の記録・リリース履歴（コミット ID） |
| `docs/TODO.md` | 次にやること |
| `docs/FACTCHECK.md` | 掲載内容の精査リスト・用語集の精査結果・確認が必要な会社情報 |
| `wp-content/themes/tenpos-kaitori/docs/SETUP.md` | テーマの導入手順 |
| `wp-content/themes/tenpos-kaitori/docs/cf7-*.txt` | CF7 のフォーム・メールのひな形 |
| `wp-content/themes/tenpos-kaitori/docs/gas/` | スプレッドシート連携（Apps Script のコードと手順） |
| `tools/` | `local-wp/setup.sh`（ローカル再現環境）、`check-pages.mjs`（15 ページ×スマホ/PC）、`check-live.sh`（本番の閲覧確認）、`gas-sim.cjs`（Apps Script の擬似実行・ローカル専用）、`wp-rest-fix.py`（2026-10-15 の一回限りの修正用。再利用しない） |
