# CLAUDE.md — 作業ルール（必ず最初に読むこと）

このリポジトリは **本番稼働中の WordPress サイト**（https://tenpos.online/kaitori/）のオリジナルテーマを管理している。
**過去に、仕様を確認せず場当たり的に対応してサイトが壊れたことがある。** 以下のルールを必ず守る。

## 作業開始時に必ず読む
1. `docs/SPEC.md` … サイト仕様（URL、slug、フィールド名、設定キー、壊しやすい箇所、既知の注意点）
2. `docs/WORKLOG.md` … これまでの作業と本番の現在の状態
3. `docs/TODO.md` … 次にやること

## 絶対に守ること
- **本番サイトに直接変更を加えない。** 本番への反映はユーザーが管理画面から行う（テーマ ZIP のアップロード等）。本番へのアクセスは閲覧（GET）による確認のみ。ログイン情報・パスワードを受け取らない／求めない。
- **本番でフォーム送信など副作用のある操作をしない**（テスト送信が必要ならユーザーに依頼）。
- **変更禁止**（変えると本番データと切れる）：固定ページ slug、ジャンル slug（agri/tool/deal）、post_type 名、taxonomy 名、ACF のフィールド name / key、カスタマイザーのキー、リライトルール。詳細は SPEC.md §3〜4。どうしても変える場合は、移行手順（既存データの付け替え）を先にユーザーと合意する。
- **推測で直さない。** 不具合の報告を受けたら、まず SPEC.md と該当コードを読み、ローカル再現環境で再現してから原因を特定する。
- **ACF のフィールドを管理画面で作らない／ACF 側で編集しない**（定義は `inc/fields.php` のみ）。
- テーマの slug（ディレクトリ名 `tenpos-kaitori`）を変えない（変えると別テーマ扱いになりカスタマイザー設定が消える）。

## 変更の手順（毎回この順番で）
1. SPEC.md で影響範囲を確認（テンプレート、JS が依存する `data-*` 属性、目次タブとセクションの順番など）
2. 変更を実装
3. 構文チェック：`for f in $(find wp-content/themes/tenpos-kaitori -name '*.php'); do php -l "$f"; done`
4. **ローカル再現環境で検証**（本番と同じ WordPress 7.1.2 ＋ ACF ＋ CF7、DB は SQLite、WP_DEBUG 有効）
   ```bash
   (cd tools && npm i)                                   # 初回のみ（Playwright）
   TMPDIR=<スクラッチパッド> tools/local-wp/setup.sh       # 環境構築・起動（何度実行しても安全。debug.log を空にする）
   node tools/check-pages.mjs http://localhost:8090/kaitori --shots=<保存先>   # 15ページ×スマホ/PC
   cat <スクラッチパッド>/tk-wp/www/kaitori/wp-content/debug.log            # 0 行であること
   ```
   - `check-pages.mjs` が「OK: 問題なし」、debug.log が空、スクリーンショットを目視確認、の3点がそろって合格
   - 変更内容に関係するページ・管理画面（ACF の入力欄など）は個別にも確認する
5. `style.css` の `Version` と `functions.php` の `TK_VERSION` を上げる
6. SPEC.md / WORKLOG.md を更新（仕様が変わったら必ず SPEC.md も直す）
7. コミット → プッシュ → タグ `theme-vX.Y.Z` → テーマ ZIP を作成してユーザーに渡す
8. ユーザーが本番に反映後、本番を読み取り確認：`tools/check-live.sh` と `node tools/check-pages.mjs https://tenpos.online/kaitori`
   - **本番を変更する前にも**同じ確認を実行し、変更前の状態を記録しておく（変更後との比較用）

## 本番への反映方法（ユーザー作業）
外観 → テーマ → 新規追加 → テーマのアップロード → ZIP を選択 →「アップロードしたもので置き換える」。
戻す場合は、一つ前のタグからZIPを作って同じ手順で置き換える：
`git archive --format=zip --prefix=tenpos-kaitori/ theme-v1.0.1:wp-content/themes/tenpos-kaitori -o tenpos-kaitori-1.0.1.zip`

## 作業環境の注意
- この環境の外向き通信はプロキシ経由。`tenpos.online`・`wordpress.org`・`downloads.wordpress.org` はネットワーク許可が必要（許可されていなければユーザーに環境設定の変更を依頼）。
- Playwright で本番を開く場合は `tools/check-pages.mjs` を使う（作業環境のプロキシを自動で使い、プロキシの CA を SPKI 指定で信頼する。TLS 検証は無効化しない）。
- ローカル再現環境のサーバーは `php -S localhost:8090 -t <作業dir>/www router.php`（setup.sh が起動）。止めるときは `pgrep -f "php -S localhost:8090"` で PID を確認して `kill`。
- `rm` の対象パスは必ず絶対パスで確認する。`/dev/null` などシステムファイルを対象にしない（2026-10-02 に誤って削除→復旧した）。
- ローカル再現環境・一時ファイルはスクラッチパッドに置き、リポジトリに入れない。

## リポジトリ構成
- `wp-content/themes/tenpos-kaitori/` … 本番テーマ（ここが正）
- `mock/` … 静的 HTML モック（デザインの元。テーマとは自動同期しない）
- `docs/` … 仕様・履歴・TODO・検証スクリーンショット
- `tools/` … ローカル再現環境・確認スクリプト
