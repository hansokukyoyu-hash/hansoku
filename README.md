# SNS閲覧数ダッシュボード

9ブランド × 6SNS(Instagram・Facebook・YouTube・Threads・TikTok・X)の閲覧数・再生回数を、期間を指定して一覧するWebシステムです。設置先は `https://tenpos.online/report/`(お名前.com レンタルサーバー、PHP 8.4 + MySQL)を想定しています。

設計の詳細は設計書(SNS再生回数ダッシュボード 設計書)を参照してください。

## できること

| 機能 | 内容 |
| --- | --- |
| ダッシュボード | 全体・SNS別のKPIカード、ブランド × SNS の比較表、前期間比、フォーマット別・投稿別の内訳(▶で展開) |
| 集計モード | ア. 期間中に発生した閲覧数 / イ. 期間中に投稿したものの閲覧数 を切り替え |
| 自動収集 | Instagram(プロ)・Facebookページ・YouTubeショート・Threads を API で取得。ストーリーズは2時間おき、その他は1日1回 |
| 手入力 | Facebook個人プロフィール・Instagram個人・TikTok・X は、各アプリで見た数値を期間ごとに入力 |
| ログイン | Googleアカウント。管理者が登録したアカウントだけが入れる |
| 権限 | 管理者(全操作)/ 運用担当(内訳・投稿別・手入力。担当ブランドに限定可)/ 閲覧者(合計のみ) |
| その他 | CSV出力、収集ログ、noindex(meta タグ + `X-Robots-Tag`) |

## 使うAPIと取得方法

| SNS | 取得方法 | 集計する数値 |
| --- | --- | --- |
| Instagram(プロ) | Instagram Graph API(Facebookログインで連携) | フィード・リール・ストーリーズの閲覧数(views) |
| Facebookページ | Facebook Graph API | 投稿・ストーリーズの閲覧数(指標名は `SNS_FACEBOOK_POST_VIEWS_METRIC` で変更可) |
| YouTube | YouTube Analytics API + Data API | ショートの再生回数(日次)と、ショートごとの累計 |
| Threads | Threads API | 投稿の閲覧数(views) |
| TikTok / X / 個人アカウント | 手入力 | 期間ごとの合計 |

Instagram・Facebook・Threads は API が投稿ごとの累計しか返さないため、毎日の累計を保存し、前日との差を日次の閲覧数としています。システムが初めて見た古い投稿は、過去分が一度に乗らないよう初日を 0 として扱います。

---

## 設置手順(お名前.com レンタルサーバー)

### 1. 事前準備:各サービスの開発者設定

戻り先URL(コールバックURL)は次のとおりです。各サービスの画面に登録してください。

| サービス | 登録するURL |
| --- | --- |
| Google(ログイン) | `https://tenpos.online/report/auth/google/callback` |
| Google(YouTube連携) | `https://tenpos.online/report/connect/google/callback` |
| Meta(Instagram・Facebook) | `https://tenpos.online/report/connect/meta/callback` |
| Threads | `https://tenpos.online/report/connect/threads/callback` |

**Google Cloud**(ログインと YouTube で同じクライアントを使います)

1. Google Cloud Console でプロジェクトを作成します。
2. 「APIとサービス」→「ライブラリ」で **YouTube Data API v3** と **YouTube Analytics API** を有効にします。
3. 「OAuth 同意画面」を設定し、公開ステータスを「本番環境」にします。「テスト」のままだと YouTube の連携が7日で切れます。
4. 「認証情報」→「OAuth クライアント ID」(ウェブアプリケーション)を作り、上の Google の2つのURLを「承認済みのリダイレクト URI」に登録します。
5. クライアントIDとシークレットを控えます。

**Meta(Instagram・Facebookページ用)**

1. Meta for Developers でアプリを作成します(ビジネス用)。
2. 「Facebookログイン」を追加し、上の Meta のURLを「有効なOAuthリダイレクトURI」に登録します。
3. 権限 `pages_show_list` `pages_read_engagement` `read_insights` `instagram_basic` `instagram_manage_insights` `business_management` を使います。
4. 連携する人を「アプリの役割」に追加します(自社アカウントだけなら開発モードのままで使える見込みです)。
5. 各 Instagram プロアカウントを Facebook ページに紐づけておきます。

**Threads**(Instagram・Facebook用とは別アプリ)

1. Meta for Developers で「アプリを作成」→ ユースケース「Threads APIにアクセス」を選びます。
2. 権限 `threads_basic` と `threads_manage_insights` を追加します。
3. 上の Threads のURLを「コールバックURL」に登録します。アンインストール・削除のコールバックURLにも `https://tenpos.online/report/` 配下のURLを入れます。
4. 「アプリの役割」→「Threadsテスター」に連携する各 Threads アカウントを追加し、各アカウントの Threads アプリ(設定 → アカウント → ウェブサイトのアクセス許可 → 招待)で承認します。
5. Threads アプリIDとシークレットを控えます。

> アプリIDやシークレットは、チャットや設計書に書かず、サーバーの `.env` にだけ書いてください。

### 2. ファイルを置く

SSH でサーバーに入り、プログラム本体は**公開フォルダの外**に置きます。`/report/` には公開用の `public` フォルダだけをシンボリックリンクで見せます。

```sh
# 例: ホーム直下に置く(パスはサーバーに合わせて読み替えてください)
cd ~
git clone https://github.com/hansokukyoyu-hash/hansoku.git sns-report
cd sns-report

# 依存パッケージ(サーバーに composer が無い場合は、手元で composer install --no-dev したものを vendor ごとアップロード)
composer install --no-dev --optimize-autoloader

# 公開フォルダから public へリンク(tenpos.online の公開フォルダのパスはコントロールパネルで確認)
ln -s ~/sns-report/public ~/public_html/tenpos.online/report
```

シンボリックリンクが使えない場合は、`public` の中身を `/report/` フォルダにコピーし、コピーした `index.php` 内の `__DIR__.'/../vendor/...'` と `__DIR__.'/../bootstrap/...'` を本体の場所を指すパスに書き換えてください。

### 3. 設定(.env)

```sh
cp .env.example .env
php artisan key:generate
vi .env   # DB、ADMIN_EMAILS、Google・Meta・Threads のIDとシークレットを入れる
chmod -R 775 storage bootstrap/cache
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

- `DB_*` には、お名前.com のコントロールパネルで作った MySQL の情報を入れます。
- `ADMIN_EMAILS` に入れた Google アカウントは、初回ログイン時に管理者として自動登録されます。
- `.env` を書き換えたら、もう一度 `php artisan config:cache` を実行します。

### 4. ベーシック認証

```sh
mkdir -p ~/sns-report-htpasswd
htpasswd -c ~/sns-report-htpasswd/.htpasswd チームで使うID
```

`public/.htaccess` の「ベーシック認証」の4行の `#` を外し、`AuthUserFile` を作った `.htpasswd` の絶対パスに書き換えます(`echo ~` でホームのパスを確認できます)。お名前.com のコントロールパネルの「アクセス制限」で設定しても構いません。

### 5. cron(自動収集とバックアップ)

コントロールパネルの cron 設定に、次の2つを登録します。PHP のパスは SSH で `which php` を実行して確認してください。

```
# 5分おきに収集(収集時期が来たアカウントを数件ずつ処理。前の回が動いていれば何もしない)
*/5 * * * * cd ~/sns-report && php artisan sns:collect >> storage/logs/cron.log 2>&1

# 毎日4:30に MySQL をバックアップ(14日分を保持)
30 4 * * * mysqldump -h DBホスト -u DBユーザー -p'DBパスワード' DB名 | gzip > ~/sns-report-backup/db_$(date +\%Y\%m\%d).sql.gz && find ~/sns-report-backup -name 'db_*.sql.gz' -mtime +14 -delete
```

1回の実行で処理する件数は `SNS_ACCOUNTS_PER_RUN`(既定3件)で調整できます。cron の実行時間の上限に当たるようなら値を小さくしてください。途中で打ち切られても、次の回が続きから処理します。

### 6. 初期設定(画面)

1. `https://tenpos.online/report/` を開き、ベーシック認証 → `ADMIN_EMAILS` の Google アカウントでログインします。
2. 「ブランド」で9ブランドを登録します。
3. 「アカウント連携」→「アカウントを追加」で、ブランドごとに SNS アカウントを登録します。API 対応の SNS は取得方法「API」、それ以外は「手入力」を選びます。
4. API のアカウントは「連携」を押し、そのアカウントを管理している人のログインで許可します。Instagram・Facebook は、連携後に表示される一覧から対象を選びます。
5. 「今すぐ収集」で取得できるか確認します。結果は「収集ログ」でも確認できます。
6. 「メンバー」でチームの Google アカウントを登録し、ロールと担当ブランドを決めます。

## 運用

- **手入力**:「手入力」画面で、Facebook個人・TikTok・X などの閲覧数を期間ごとに入力します(例:毎月初に前月分)。ダッシュボードでは、集計期間と重なる日数で按分して合計に含めます。モード「イ」と投稿別の内訳には含まれません。
- **連携切れ**:ダッシュボードの「最終更新」や「アカウント連携」画面に「取得エラー」「再連携が必要」と出たら、「再連携」を押してください。Threads(60日)と YouTube の連携は自動で更新されます。
- **手動で収集**:`php artisan sns:collect --account=アカウントID` で、そのアカウントだけを今すぐ収集できます。

## 開発

```sh
composer install
cp .env.example .env   # APP_ENV=local, DB_CONNECTION=sqlite に変更
php artisan key:generate
php artisan migrate
php artisan db:seed --class=DemoSeeder   # 画面確認用のサンプルデータ
php artisan test
```
