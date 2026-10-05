<?php
/**
 * 問い合わせ・査定依頼を Google スプレッドシートへ自動追記（Apps Script 連携）
 *
 * - CF7 のメール送信成功（wpcf7_mail_sent）後に、送信内容を Apps Script のウェブアプリへ POST
 * - 設定：外観 → カスタマイズ → スプレッドシート連携（URL・トークン・オン／オフ）
 * - 送信先は https://script.google.com/ のみ（ローカル検証時はフィルター tk_sheets_allow_url で許可）
 * - 失敗しても問い合わせ自体は Flamingo・メールに残る。失敗は管理画面に通知
 * - 写真ファイルは送らない（「あり（メール添付）」とだけ記録）
 *
 * シート側のコードと手順：docs/gas/Code.gs・docs/gas/README.md
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

/**
 * 送信先 URL が許可されたものか
 */
function tk_sheets_url_ok( string $url ): bool {
	$ok = (bool) preg_match( '#^https://script\.google\.com/macros/s/[A-Za-z0-9_-]+/exec$#', $url );
	return (bool) apply_filters( 'tk_sheets_allow_url', $ok, $url );
}

/**
 * CF7 フォーム ID → 種別
 */
function tk_sheets_form_type( int $form_id ): string {
	foreach ( array(
		'agri'    => 'tk_cf7_agri',
		'tool'    => 'tk_cf7_tool',
		'contact' => 'tk_cf7_contact',
	) as $type => $key ) {
		if ( (int) tk_opt( $key ) === $form_id ) {
			return $type;
		}
	}
	return '';
}

/**
 * 送信内容 → シート 1 行分のデータ
 *
 * @param WPCF7_ContactForm $form       フォーム.
 * @param WPCF7_Submission  $submission 送信.
 */
function tk_sheets_payload( $form, $submission ): array {
	$posted = $submission->get_posted_data();
	$get    = function ( string $key ) use ( $posted ): string {
		$v = $posted[ $key ] ?? '';
		return trim( is_array( $v ) ? implode( '、', $v ) : (string) $v );
	};
	$type   = tk_sheets_form_type( (int) $form->id() );
	$labels = array(
		'agri'    => '農機具査定',
		'tool'    => '工具査定',
		'contact' => '総合問い合わせ',
	);
	$files  = array_filter( (array) $submission->uploaded_files() );

	return array(
		'id'           => 'TK-' . wp_date( 'Ymd-His' ) . '-' . strtoupper( wp_generate_password( 4, false ) ),
		'submitted_at' => wp_date( 'c' ),
		'form_label'   => $labels[ $type ] ?? $form->title(),
		'name'         => $get( 'your-name' ),
		'tel'          => $get( 'your-tel' ),
		'email'        => $get( 'your-email' ),
		'pref'         => $get( 'your-pref' ),
		'kind'         => $get( 'your-kind' ),
		'model'        => $get( 'your-model' ),
		'message'      => $get( 'your-message' ),
		'photo'        => $files ? 'あり（メール添付）' : '',
		'page_url'     => (string) $submission->get_meta( 'url' ),
	);
}

add_action(
	'wpcf7_mail_sent',
	function ( $form ) {
		if ( '1' !== tk_opt( 'tk_sheets_enable' ) ) {
			return;
		}
		$url   = trim( tk_opt( 'tk_sheets_url' ) );
		$token = trim( tk_opt( 'tk_sheets_token' ) );
		if ( ! $url || ! $token || ! tk_sheets_url_ok( $url ) || ! class_exists( 'WPCF7_Submission' ) ) {
			return;
		}
		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission || ! tk_sheets_form_type( (int) $form->id() ) ) {
			return; // テーマの 3 フォーム以外は送らない.
		}

		$payload = tk_sheets_payload( $form, $submission ) + array( 'token' => $token );
		$res     = wp_remote_post(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 3, // Apps Script は 302 で結果ページへ転送する。転送先の JSON で成否を判定.
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'        => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE ),
			)
		);

		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		$body = is_wp_error( $res ) ? '' : (string) wp_remote_retrieve_body( $res );
		$ok   = 200 === $code && str_contains( $body, '"ok":true' );

		if ( $ok ) {
			delete_option( 'tk_sheets_last_error' );
			update_option( 'tk_sheets_last_ok', wp_date( 'Y-m-d H:i' ), false );
		} else {
			$msg = is_wp_error( $res ) ? $res->get_error_message() : "HTTP {$code} " . wp_strip_all_tags( mb_substr( $body, 0, 120 ) );
			update_option( 'tk_sheets_last_error', wp_date( 'Y-m-d H:i' ) . ' ' . $msg . '（受付ID ' . $payload['id'] . '）', false );
			error_log( '[tenpos-kaitori] スプレッドシート送信失敗: ' . $msg ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
);

/**
 * 送信失敗の通知（管理者のみ・次に成功するまで表示）
 */
add_action(
	'admin_notices',
	function () {
		$err = get_option( 'tk_sheets_last_error' );
		if ( ! $err || ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p><strong>スプレッドシートへの自動追記に失敗しました。</strong>問い合わせ内容は Flamingo とメールに保存されています。<br>%s<br>設定：外観 → カスタマイズ → スプレッドシート連携</p></div>',
			esc_html( $err )
		);
	}
);
