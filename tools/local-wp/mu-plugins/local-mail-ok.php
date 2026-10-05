<?php
/**
 * ローカル検証専用：メール送信を成功扱いにする（本番には絶対に入れない）
 *
 * ローカル再現環境にはメールサーバーがないため、CF7 の送信が常に「失敗」になり
 * 送信完了（wpcf7mailsent）の表示・計測を検証できない。wp_mail を送らずに成功を返す。
 * 送信内容は debug 用に wp-content/local-mail.log に追記する。
 */
add_filter(
	'pre_wp_mail',
	function ( $null, $atts ) {
		file_put_contents( WP_CONTENT_DIR . '/local-mail.log', gmdate( 'c' ) . ' TO:' . ( is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : $atts['to'] ) . ' SUBJECT:' . $atts['subject'] . "\n" . $atts['message'] . "\n----\n", FILE_APPEND );
		return true;
	},
	10,
	2
);
