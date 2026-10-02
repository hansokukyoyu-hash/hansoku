<?php
/**
 * テーマの基本設定
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	function () {
		load_theme_textdomain( 'tenpos-kaitori', TK_DIR . '/languages' );

		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 80,
				'width'       => 158,
				'flex-width'  => true,
				'flex-height' => true,
			)
		);

		add_image_size( 'tk-card', 640, 480, true );
		add_image_size( 'tk-wide', 1280, 720, true );

		register_nav_menus(
			array(
				'global' => 'グローバルナビ（PCヘッダー）',
				'footer' => 'フッター（サポート）',
			)
		);
	}
);

/**
 * 表示速度対策：使わない標準出力を外す
 */
add_action(
	'init',
	function () {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_generator' );
	}
);

/**
 * ページ種別ごとのテーマトーン（body クラス）
 */
add_filter(
	'body_class',
	function ( $classes ) {
		$classes[] = 'theme-' . tk_theme_tone();
		return $classes;
	}
);

/**
 * 抜粋の長さ（全角想定）
 */
add_filter( 'excerpt_length', fn() => 60 );
add_filter( 'excerpt_more', fn() => '…' );

/**
 * 用語集・FAQ は 1 ページに全件表示、コラム一覧は 12 件
 */
add_action(
	'pre_get_posts',
	function ( WP_Query $q ) {
		if ( is_admin() || ! $q->is_main_query() ) {
			return;
		}
		if ( $q->is_post_type_archive( array( 'glossary', 'faq' ) ) ) {
			$q->set( 'posts_per_page', -1 );
			$q->set( 'orderby', $q->is_post_type_archive( 'glossary' ) ? 'meta_value' : 'menu_order' );
			$q->set( 'order', 'ASC' );
			if ( $q->is_post_type_archive( 'glossary' ) ) {
				$q->set( 'meta_key', 'gl_yomi' );
			}
		}
		if ( $q->is_post_type_archive( 'result' ) || $q->is_tax( 'genre' ) && 'result' === $q->get( 'post_type' ) ) {
			$q->set( 'posts_per_page', 12 );
		}
		if ( $q->is_post_type_archive( 'column' ) ) {
			$q->set( 'posts_per_page', 12 );
		}
		if ( $q->is_tax( 'series' ) ) {
			$q->set( 'posts_per_page', -1 );
		}
	}
);

/**
 * 用語の個別ページは用語集の該当位置へ転送（薄いページを作らない）
 */
add_action(
	'template_redirect',
	function () {
		if ( is_singular( 'glossary' ) ) {
			wp_safe_redirect( get_post_type_archive_link( 'glossary' ) . '#' . get_post_field( 'post_name' ), 301 );
			exit;
		}
	}
);
