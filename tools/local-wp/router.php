<?php
// php -S 用ルーター（/kaitori/ 配下の WordPress をパーマリンク付きで動かす）
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$file = __DIR__ . '/www' . $path;
if ( is_file( $file ) ) {
	return false;
}
$dir = rtrim( $file, '/' );
if ( is_dir( $dir ) && is_file( $dir . '/index.php' ) ) {
	$_SERVER['SCRIPT_NAME']     = rtrim( $path, '/' ) . '/index.php';
	$_SERVER['SCRIPT_FILENAME'] = $dir . '/index.php';
	chdir( $dir );
	require $dir . '/index.php';
	return;
}
$_SERVER['SCRIPT_NAME']     = '/kaitori/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/www/kaitori/index.php';
chdir( __DIR__ . '/www/kaitori' );
require __DIR__ . '/www/kaitori/index.php';
