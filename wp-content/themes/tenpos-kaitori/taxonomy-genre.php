<?php
/**
 * ジャンル別一覧：実績のみ実績一覧テンプレートで表示
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

if ( 'result' === get_query_var( 'post_type' ) ) {
	require __DIR__ . '/archive-result.php';
	return;
}
require __DIR__ . '/index.php';
