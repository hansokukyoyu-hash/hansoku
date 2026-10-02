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
	}
);
