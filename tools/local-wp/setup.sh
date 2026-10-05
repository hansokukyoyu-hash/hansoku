#!/usr/bin/env bash
# 本番と同じ構成（WordPress 7.1.2 + ACF + CF7、SQLite）をローカルに再現し、テーマを有効化する。
# 使い方: tools/local-wp/setup.sh [作業ディレクトリ] [ポート]
#   既定: 作業ディレクトリ = $TMPDIR/tk-wp（リポジトリの外）、ポート = 8090
# 何度実行しても安全（既にあるものは再利用）。削除は行わない。
set -euo pipefail

REPO="$(cd "$(dirname "$0")/../.." && pwd)"
BASE="${1:-${TMPDIR:-/tmp}/tk-wp}"
PORT="${2:-8090}"
WPVER="7.1.2"
W="$BASE/www/kaitori"
URL="http://localhost:$PORT/kaitori"
CLI="$BASE/wp-cli.phar"
WP="php $CLI --allow-root --path=$W"

mkdir -p "$W"
[ -f "$CLI" ] || curl -sS -o "$CLI" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
[ -f "$W/wp-includes/version.php" ] || $WP core download --version="$WPVER" --locale=ja

for p in sqlite-database-integration advanced-custom-fields contact-form-7; do
  if [ ! -d "$W/wp-content/plugins/$p" ]; then
    curl -sS -o "$BASE/$p.zip" "https://downloads.wordpress.org/plugin/$p.zip"
    unzip -qo "$BASE/$p.zip" -d "$W/wp-content/plugins/"
  fi
done

SQ="$W/wp-content/plugins/sqlite-database-integration"
[ -f "$W/wp-content/db.php" ] || sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$SQ#" -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" "$SQ/db.copy" > "$W/wp-content/db.php"

if [ ! -f "$W/wp-config.php" ]; then
  cp "$W/wp-config-sample.php" "$W/wp-config.php"
  sed -i "s/database_name_here/wp/;s/username_here/wp/;s/password_here/wp/" "$W/wp-config.php"
  sed -i "s/define( 'WP_DEBUG', false );/define( 'WP_DEBUG', true ); define( 'WP_DEBUG_LOG', true ); define( 'WP_DEBUG_DISPLAY', false ); define( 'DB_FILE', 'local.sqlite' );/" "$W/wp-config.php"
fi

ln -sfn "$REPO/wp-content/themes/tenpos-kaitori" "$W/wp-content/themes/tenpos-kaitori"
# ローカル専用：メール送信を成功扱いにする（送信完了の表示・計測を検証するため。本番には入れない）
mkdir -p "$W/wp-content/mu-plugins"
cp "$REPO/tools/local-wp/mu-plugins/local-mail-ok.php" "$W/wp-content/mu-plugins/local-mail-ok.php"
cp "$REPO/tools/local-wp/router.php" "$BASE/router.php"

if ! curl -s -o /dev/null "http://localhost:$PORT/"; then
  (cd "$BASE" && setsid nohup php -S "localhost:$PORT" -t "$BASE/www" router.php > "$BASE/php-server.log" 2>&1 < /dev/null &)
  sleep 1
fi

if ! $WP core is-installed 2>/dev/null; then
  $WP core install --url="$URL" --title="テンポス買取（ローカル）" --admin_user=admin --admin_password=localonly --admin_email=dev@example.com --skip-email
  $WP plugin activate advanced-custom-fields contact-form-7
  $WP theme activate tenpos-kaitori
  $WP rewrite structure '/%postname%/'
  $WP option update timezone_string Asia/Tokyo
  $WP eval 'tk_ensure_genres(); print_r( tk_setup_pages() ); echo tk_setup_privacy_page(), "\n"; print_r( tk_import_samples() );'
  # 本番に合わせてプライバシーポリシーを公開状態にする（ローカルのみ）
  $WP eval '$id = (int) get_option( "wp_page_for_privacy_policy" ); if ( $id ) { wp_update_post( array( "ID" => $id, "post_status" => "publish" ) ); }'
  $WP eval '
    foreach ( array( "agri" => "農機具査定", "tool" => "工具査定", "contact" => "総合問い合わせ" ) as $k => $t ) {
      $f = WPCF7_ContactForm::get_template( array( "title" => $t ) );
      $f->set_properties( array( "form" => file_get_contents( get_template_directory() . "/docs/cf7-" . $k . ".txt" ) ) );
      set_theme_mod( "tk_cf7_" . $k, (string) $f->save() );
    }'
fi

# php ビルトインサーバーの初回アクセスで siteurl が化けることがあるので毎回補正
$WP option update home "$URL" >/dev/null
$WP option update siteurl "$URL" >/dev/null
$WP rewrite flush >/dev/null

echo "ローカル環境: $URL  （管理画面 admin / localonly）"
# WP-CLI 実行時の警告（HTTP_HOST 等）が混ざるので、確認前に debug.log を空にする
: > "$W/wp-content/debug.log"
echo "debug.log : $W/wp-content/debug.log（空にしました。ページ確認後に中身を見る）"
