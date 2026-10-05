<?php
/**
 * カスタマイザー：電話番号・会社情報・CF7 フォーム ID
 *
 * 外観 → カスタマイズ → 「買取サイト設定」
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'customize_register',
	function ( WP_Customize_Manager $wp_customize ) {
		$wp_customize->add_section(
			'tk_site',
			array(
				'title'    => '買取サイト設定',
				'priority' => 30,
			)
		);

		$fields = array(
			'tk_tel'         => array( '電話番号（フリーダイヤル）', '0120-000-000', 'text' ),
			'tk_tel_hours'   => array( '電話受付時間', '受付 8:00〜20:00／年中無休', 'text' ),
			'tk_company'     => array( '運営会社表記', '株式会社テンポスバスターズ（東証上場グループ）', 'text' ),
			'tk_license'     => array( '古物商許可番号', '東京都公安委員会 第000000000000号', 'text' ),
			'tk_cf7_agri'    => array( 'CF7 フォームID：農機具 査定', '', 'text' ),
			'tk_cf7_tool'    => array( 'CF7 フォームID：工具 査定', '', 'text' ),
			'tk_cf7_contact' => array( 'CF7 フォームID：共通お問い合わせ', '', 'text' ),
		);

		foreach ( $fields as $id => [ $label, $default, $type ] ) {
			$wp_customize->add_setting(
				$id,
				array(
					'default'           => $default,
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
			$wp_customize->add_control(
				$id,
				array(
					'label'       => $label,
					'section'     => 'tk_site',
					'type'        => $type,
					'description' => str_starts_with( $id, 'tk_cf7' ) ? 'Contact Form 7 のショートコード [contact-form-7 id="…"] の id の値を入力' : '',
				)
			);
		}

		// スプレッドシート連携（問い合わせ・査定依頼の自動追記）.
		$tk_last_pull = get_option( 'tk_sheets_last_pull' );
		$tk_pending   = tk_sheets_pending();
		$wp_customize->add_section(
			'tk_sheets',
			array(
				'title'       => 'スプレッドシート連携',
				'priority'    => 32,
				'description' => '査定・問い合わせフォームの送信内容を、スプレッドシートの Apps Script が5分ごとに取りに来て追記します。設定手順はテーマの docs/gas/README.md。'
					. "\n最終取得：" . ( $tk_last_pull ? $tk_last_pull : 'なし' )
					. "\n未反映：{$tk_pending['count']} 件",
			)
		);
		$wp_customize->add_setting(
			'tk_sheets_enable',
			array(
				'default'           => '',
				'sanitize_callback' => fn( $v ) => $v ? '1' : '',
			)
		);
		$wp_customize->add_control(
			'tk_sheets_enable',
			array(
				'label'   => 'スプレッドシートへの自動追記を有効にする',
				'section' => 'tk_sheets',
				'type'    => 'checkbox',
			)
		);
		$wp_customize->add_setting(
			'tk_sheets_token',
			array(
				'default'           => '',
				'sanitize_callback' => fn( $v ) => preg_replace( '/[^A-Za-z0-9]/', '', (string) $v ),
			)
		);
		$wp_customize->add_control(
			'tk_sheets_token',
			array(
				'label'       => 'トークン（合言葉）',
				'description' => 'Apps Script の setup 実行時にログに表示された値（20 文字未満では動きません）',
				'section'     => 'tk_sheets',
				'type'        => 'text',
			)
		);

		// 農機具 LP：アフリカで再活用（当面の主訴求）.
		$d = tk_africa_defaults();
		$wp_customize->add_section(
			'tk_africa',
			array(
				'title'       => '農機具LP：アフリカ訴求',
				'priority'    => 31,
				'description' => '農機具買取ページのファーストビューの帯と「アフリカで再活用」セクションの内容。国名・台数などの数字は、根拠資料があるものだけ記載してください。',
			)
		);
		$wp_customize->add_setting(
			'tk_africa_enable',
			array(
				'default'           => $d['tk_africa_enable'],
				'sanitize_callback' => fn( $v ) => $v ? '1' : '',
			)
		);
		$wp_customize->add_control(
			'tk_africa_enable',
			array(
				'label'   => 'アフリカ訴求を表示する（ファーストビューの帯・セクション・目次タブ）',
				'section' => 'tk_africa',
				'type'    => 'checkbox',
			)
		);
		foreach ( array(
			'tk_africa_badge' => array( 'ファーストビューの帯の文言', 'text', 'sanitize_text_field', '' ),
			'tk_africa_title' => array( 'セクション見出し', 'textarea', 'sanitize_textarea_field', '改行すると見出しも改行されます' ),
			'tk_africa_lead'  => array( 'セクション本文（結論の一文）', 'textarea', 'sanitize_textarea_field', '' ),
		) as $id => [ $label, $type, $sanitize, $desc ] ) {
			$wp_customize->add_setting(
				$id,
				array(
					'default'           => $d[ $id ],
					'sanitize_callback' => $sanitize,
				)
			);
			$wp_customize->add_control(
				$id,
				array(
					'label'       => $label,
					'section'     => 'tk_africa',
					'type'        => $type,
					'description' => $desc,
				)
			);
		}
		$wp_customize->add_setting(
			'tk_africa_image',
			array(
				'default'           => '',
				'sanitize_callback' => 'absint',
			)
		);
		$wp_customize->add_control(
			new WP_Customize_Media_Control(
				$wp_customize,
				'tk_africa_image',
				array(
					'label'       => 'セクションの写真（任意）',
					'description' => '未設定の場合は「日本→アフリカ」の図を表示します。',
					'section'     => 'tk_africa',
					'mime_type'   => 'image',
				)
			)
		);
	}
);
