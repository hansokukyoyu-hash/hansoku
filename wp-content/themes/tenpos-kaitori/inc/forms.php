<?php
/**
 * 査定フォーム（Contact Form 7）
 *
 * フォーム本文のひな形は docs/cf7-*.txt を CF7 の「フォーム」タブに貼り付けて使います。
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

// CF7 の CSS はテーマ側で用意しているため読み込まない.
add_filter( 'wpcf7_load_css', '__return_false' );
// CF7 の JS はフォームを表示するページだけで読み込む.
add_filter( 'wpcf7_load_js', '__return_false' );

/**
 * CF7 フォームを出力（未設定時は電話導線と管理者向け案内）
 *
 * @param string $key tk_cf7_agri / tk_cf7_tool / tk_cf7_contact.
 */
function tk_render_cf7( string $key ): void {
	$id = trim( tk_opt( $key ) );

	if ( $id && shortcode_exists( 'contact-form-7' ) ) {
		if ( function_exists( 'wpcf7_enqueue_scripts' ) ) {
			wpcf7_enqueue_scripts();
		}
		echo do_shortcode( sprintf( '[contact-form-7 id="%s"]', esc_attr( $id ) ) );
		return;
	}

	echo '<div class="form-fallback">';
	echo '<p>ただいまフォームを準備中です。お急ぎの方はお電話でご相談ください。</p>';
	printf( '<a class="btn btn--primary" href="%s">%s%s</a>', esc_url( tk_tel_href() ), tk_icon( 'phone' ), esc_html( tk_opt( 'tk_tel' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- tk_icon は固定マークアップ.
	if ( current_user_can( 'edit_theme_options' ) ) {
		echo '<p class="form-fallback__admin">【管理者のみ表示】Contact Form 7 を有効化し、外観 → カスタマイズ → 買取サイト設定 でフォームIDを設定してください。</p>';
	}
	echo '</div>';
}

/**
 * LP 下部の査定セクション（フォーム＋安心の約束）
 */
function tk_render_estimate_section( string $genre ): void {
	get_template_part( 'parts/estimate', null, array( 'genre' => $genre ) );
}
