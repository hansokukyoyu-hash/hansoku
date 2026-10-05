<?php
/**
 * 問い合わせ・査定依頼を Google スプレッドシートへ自動追記（Apps Script が取りに来る方式）
 *
 * - CF7 のメール送信成功（wpcf7_mail_sent）後に、送信内容を「未反映」として一時保管（非公開の投稿タイプ tk_sheet_row）
 * - スプレッドシートの Apps Script が数分おきに REST API（POST /wp-json/tk/v1/sheets/pull）で取りに来て、
 *   シートに追記できたものを POST /wp-json/tk/v1/sheets/ack で知らせる → 一時保管から削除
 * - サイトから Google へは送らない（Workspace の「組織内のみ」設定でも動く）
 * - 認証：トークン（カスタマイザーと Apps Script の Script Properties `TOKEN` が同じ値）
 * - 設定：外観 → カスタマイズ → スプレッドシート連携（オン／オフ・トークン）
 * - 写真ファイルは送らない（「あり（メール添付）」とだけ記録）
 *
 * シート側のコードと手順：docs/gas/Code.gs・docs/gas/README.md
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

const TK_SHEETS_TYPE  = 'tk_sheet_row';
const TK_SHEETS_BATCH = 50;

add_action(
	'init',
	function () {
		register_post_type(
			TK_SHEETS_TYPE,
			array(
				'label'               => 'スプレッドシート未反映',
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'query_var'           => false,
				'rewrite'             => false,
				'can_export'          => false,
				'delete_with_user'    => false,
				'supports'            => array( 'title' ),
			)
		);
	}
);

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

/**
 * 連携が有効か（オン かつ トークン 20 文字以上）
 */
function tk_sheets_enabled(): bool {
	return '1' === tk_opt( 'tk_sheets_enable' ) && strlen( trim( tk_opt( 'tk_sheets_token' ) ) ) >= 20; // 短い合言葉は総当たりされるので無効.
}

/**
 * 未反映の件数・最も古い日時
 *
 * @return array{count:int, oldest:string}
 */
function tk_sheets_pending(): array {
	$ids = get_posts(
		array(
			'post_type'        => TK_SHEETS_TYPE,
			'post_status'      => 'private',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'suppress_filters' => true,
		)
	);
	return array(
		'count'  => count( $ids ),
		'oldest' => $ids ? (string) get_post_field( 'post_date', $ids[0] ) : '',
	);
}

// 送信成功 → 未反映として一時保管.
add_action(
	'wpcf7_mail_sent',
	function ( $form ) {
		if ( ! tk_sheets_enabled() || ! class_exists( 'WPCF7_Submission' ) ) {
			return;
		}
		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission || ! tk_sheets_form_type( (int) $form->id() ) ) {
			return; // テーマの 3 フォーム以外は保管しない.
		}
		$payload = tk_sheets_payload( $form, $submission );
		$id      = wp_insert_post(
			array(
				'post_type'   => TK_SHEETS_TYPE,
				'post_status' => 'private',
				'post_title'  => $payload['id'],
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			error_log( '[tenpos-kaitori] スプレッドシート用の保管に失敗: ' . $id->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return;
		}
		// 本文ではなくメタに保存（本文は未ログイン時に kses で書き換えられるため）. 値の \ を残すため wp_slash.
		add_post_meta( $id, '_tk_row', wp_slash( $payload ), true );
	}
);

/**
 * REST の認証（トークン一致・連携オン）
 *
 * @param WP_REST_Request $req リクエスト.
 * @return true|WP_Error
 */
function tk_sheets_rest_auth( WP_REST_Request $req ) {
	$token = trim( tk_opt( 'tk_sheets_token' ) );
	$given = (string) $req->get_param( 'token' );
	if ( tk_sheets_enabled() && '' !== $given && hash_equals( $token, $given ) ) {
		return true;
	}
	return new WP_Error( 'tk_unauthorized', 'unauthorized', array( 'status' => 403 ) );
}

add_action(
	'rest_api_init',
	function () {
		// 未反映を古い順に最大 TK_SHEETS_BATCH 件.
		register_rest_route(
			'tk/v1',
			'/sheets/pull',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'tk_sheets_rest_auth',
				'callback'            => function () {
					$ids  = get_posts(
						array(
							'post_type'        => TK_SHEETS_TYPE,
							'post_status'      => 'private',
							'numberposts'      => TK_SHEETS_BATCH + 1,
							'fields'           => 'ids',
							'orderby'          => 'ID',
							'order'            => 'ASC',
							'suppress_filters' => true,
						)
					);
					$more = count( $ids ) > TK_SHEETS_BATCH;
					$rows = array();
					foreach ( array_slice( $ids, 0, TK_SHEETS_BATCH ) as $id ) {
						$row = get_post_meta( $id, '_tk_row', true );
						if ( is_array( $row ) ) {
							$rows[] = array( 'qid' => $id ) + $row;
						}
					}
					update_option( 'tk_sheets_last_pull', wp_date( 'Y-m-d H:i' ), false );
					$res = rest_ensure_response(
						array(
							'ok'   => true,
							'rows' => $rows,
							'more' => $more,
						)
					);
					$res->header( 'Cache-Control', 'no-store' );
					return $res;
				},
			)
		);
		// シートに追記できたものを削除.
		register_rest_route(
			'tk/v1',
			'/sheets/ack',
			array(
				'methods'             => 'POST',
				'permission_callback' => 'tk_sheets_rest_auth',
				'callback'            => function ( WP_REST_Request $req ) {
					$deleted = 0;
					foreach ( array_slice( (array) $req->get_param( 'ids' ), 0, 500 ) as $id ) {
						$id = (int) $id;
						if ( $id > 0 && TK_SHEETS_TYPE === get_post_type( $id ) && wp_delete_post( $id, true ) ) {
							++$deleted;
						}
					}
					$res = rest_ensure_response(
						array(
							'ok'      => true,
							'deleted' => $deleted,
						)
					);
					$res->header( 'Cache-Control', 'no-store' );
					return $res;
				},
			)
		);
	}
);

/**
 * 取りに来ていないときの通知（管理者のみ）：連携オンで、30 分以上前の未反映が残っている
 */
add_action(
	'admin_notices',
	function () {
		if ( ! tk_sheets_enabled() || ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		$p = tk_sheets_pending();
		if ( ! $p['count'] || strtotime( $p['oldest'] ) > strtotime( current_time( 'mysql' ) ) - 30 * MINUTE_IN_SECONDS ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p><strong>スプレッドシートに反映されていない問い合わせが %d 件あります（最も古いもの：%s）。</strong>問い合わせ内容は Flamingo とメールに保存されています。<br>スプレッドシートの Apps Script（関数 pull と 5 分ごとのトリガー）を確認してください。最終取得：%s</p></div>',
			(int) $p['count'],
			esc_html( $p['oldest'] ),
			esc_html( get_option( 'tk_sheets_last_pull' ) ? get_option( 'tk_sheets_last_pull' ) : 'なし' )
		);
	}
);

// 1.4.x（サイトから送る方式）の記録を片付ける.
add_action(
	'admin_init',
	function () {
		if ( false !== get_option( 'tk_sheets_last_error' ) || false !== get_option( 'tk_sheets_last_ok' ) ) {
			delete_option( 'tk_sheets_last_error' );
			delete_option( 'tk_sheets_last_ok' );
		}
	}
);
