<?php
/**
 * CSS / JS の読み込み
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_enqueue_scripts',
	function () {
		$tone  = tk_theme_tone();
		$fonts = 'https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&family=Zen+Kaku+Gothic+New:wght@500;700;900' . ( 'tool' === $tone ? '&family=Anton' : '' ) . '&display=swap';

		wp_enqueue_style( 'tk-fonts', $fonts, array(), null );
		wp_enqueue_style( 'tk-main', TK_URI . '/assets/css/main.css', array(), (string) filemtime( TK_DIR . '/assets/css/main.css' ) );
		wp_enqueue_script(
			'tk-main',
			TK_URI . '/assets/js/main.js',
			array(),
			(string) filemtime( TK_DIR . '/assets/js/main.js' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		// 不要なブロック用 CSS を外す（本文にブロックを使うコラム・固定ページ以外）.
		if ( ! is_singular( array( 'column', 'page', 'post', 'result' ) ) || is_front_page() ) {
			wp_dequeue_style( 'wp-block-library' );
			wp_dequeue_style( 'global-styles' );
			wp_dequeue_style( 'classic-theme-styles' );
		}
	},
	20
);

/**
 * Google Fonts への事前接続
 */
add_filter(
	'wp_resource_hints',
	function ( $urls, $relation ) {
		if ( 'preconnect' === $relation ) {
			$urls[] = 'https://fonts.googleapis.com';
			$urls[] = array(
				'href'        => 'https://fonts.gstatic.com',
				'crossorigin' => 'anonymous',
			);
		}
		return $urls;
	},
	10,
	2
);

/**
 * ブロックエディタにもテーマの見た目を少し反映
 */
add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'editor-styles' );
		add_editor_style( 'assets/css/editor.css' );
	}
);
