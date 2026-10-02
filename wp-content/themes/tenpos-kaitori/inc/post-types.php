<?php
/**
 * カスタム投稿タイプ・タクソノミー
 *
 * result   … 買取実績        /results/
 * column   … コラム          /column/
 * glossary … 用語集          /glossary/
 * faq      … よくある質問    /faq/
 * genre    … ジャンル（農機具 agri / 工具 tool / 買取・手続き deal）
 * series   … コラムの連載    /column/series/{slug}/
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	function () {
		$common = array(
			'public'       => true,
			'show_in_rest' => true,
			'menu_position' => 5,
		);

		// タクソノミーを先に登録：/column/series/{slug}/ のルールを /column/{post}/ より優先させる.
		register_taxonomy(
			'genre',
			array( 'result', 'column', 'glossary', 'faq' ),
			array(
				'label'             => 'ジャンル',
				'hierarchical'      => true,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'genre', 'with_front' => false ),
			)
		);

		register_taxonomy(
			'series',
			array( 'column' ),
			array(
				'label'             => '連載',
				'labels'            => array( 'add_new_item' => '連載を追加' ),
				'hierarchical'      => true,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'column/series', 'with_front' => false ),
			)
		);

		register_post_type(
			'result',
			$common + array(
				'label'       => '買取実績',
				'labels'      => array( 'add_new_item' => '買取実績を追加', 'all_items' => '買取実績一覧' ),
				'menu_icon'   => 'dashicons-awards',
				'has_archive' => 'results',
				'rewrite'     => array( 'slug' => 'results', 'with_front' => false ),
				'supports'    => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			)
		);

		register_post_type(
			'column',
			$common + array(
				'label'       => 'コラム',
				'labels'      => array( 'add_new_item' => 'コラムを追加', 'all_items' => 'コラム一覧' ),
				'menu_icon'   => 'dashicons-welcome-write-blog',
				'has_archive' => 'column',
				'rewrite'     => array( 'slug' => 'column', 'with_front' => false ),
				'supports'    => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'author' ),
			)
		);

		register_post_type(
			'glossary',
			$common + array(
				'label'       => '用語集',
				'labels'      => array( 'add_new_item' => '用語を追加', 'all_items' => '用語一覧' ),
				'menu_icon'   => 'dashicons-book-alt',
				'has_archive' => 'glossary',
				'rewrite'     => array( 'slug' => 'glossary', 'with_front' => false ),
				'supports'    => array( 'title', 'editor' ),
			)
		);

		register_post_type(
			'faq',
			$common + array(
				'label'       => 'よくある質問',
				'labels'      => array( 'add_new_item' => '質問を追加', 'all_items' => '質問一覧' ),
				'menu_icon'   => 'dashicons-editor-help',
				'has_archive' => 'faq',
				'rewrite'     => array( 'slug' => 'faq', 'with_front' => false ),
				'supports'    => array( 'title', 'editor', 'page-attributes' ),
				'publicly_queryable' => true,
			)
		);

		// LP 配下の実績一覧：/agricultural-equipment/results/ ・ /tool/results/
		add_rewrite_rule( '^agricultural-equipment/results/?(?:page/([0-9]+)/?)?$', 'index.php?post_type=result&genre=agri&paged=$matches[1]', 'top' );
		add_rewrite_rule( '^tool/results/?(?:page/([0-9]+)/?)?$', 'index.php?post_type=result&genre=tool&paged=$matches[1]', 'top' );
	}
);

/**
 * ジャンルは管理画面で1つだけ選ぶ運用（初期タームを用意）
 */
function tk_ensure_genres(): void {
	foreach ( array(
		'agri' => '農機具',
		'tool' => '工具',
		'deal' => '買取・手続き',
	) as $slug => $name ) {
		if ( ! term_exists( $slug, 'genre' ) ) {
			wp_insert_term( $name, 'genre', array( 'slug' => $slug ) );
		}
	}
}

add_action(
	'after_switch_theme',
	function () {
		tk_ensure_genres();
		flush_rewrite_rules();
	}
);

/**
 * ジャンル別の実績一覧 URL
 */
function tk_results_url( string $genre = '' ): string {
	if ( 'agri' === $genre ) {
		return home_url( '/agricultural-equipment/results/' );
	}
	if ( 'tool' === $genre ) {
		return home_url( '/tool/results/' );
	}
	return (string) get_post_type_archive_link( 'result' );
}

/**
 * 管理画面一覧：実績の価格・コラムの回数を列に表示
 */
add_filter(
	'manage_result_posts_columns',
	fn( $c ) => array_merge( $c, array( 'tk_price' => '買取価格' ) )
);
add_action(
	'manage_result_posts_custom_column',
	function ( $col, $id ) {
		if ( 'tk_price' === $col ) {
			$v = tk_field( 'rs_price', $id );
			echo $v ? esc_html( number_format( (int) $v ) . '円' ) : '—';
		}
	},
	10,
	2
);
add_filter(
	'manage_column_posts_columns',
	fn( $c ) => array_merge( $c, array( 'tk_ep' => '回' ) )
);
add_action(
	'manage_column_posts_custom_column',
	function ( $col, $id ) {
		if ( 'tk_ep' === $col ) {
			$v = tk_field( 'col_episode', $id );
			echo $v ? esc_html( '第' . (int) $v . '回' ) : '—';
		}
	},
	10,
	2
);
