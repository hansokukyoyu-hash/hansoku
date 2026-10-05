<?php
/**
 * テンプレート用ヘルパー
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

/**
 * カスタムフィールド取得（ACF があれば ACF、なければ post meta）
 *
 * @param string          $key  フィールド名.
 * @param int|string|null $id   投稿ID または 'term_{id}'.
 * @return mixed
 */
function tk_field( string $key, $id = null ) {
	if ( null === $id ) {
		$id = get_the_ID();
	}
	if ( function_exists( 'get_field' ) ) {
		return get_field( $key, $id );
	}
	if ( is_string( $id ) && str_starts_with( $id, 'term_' ) ) {
		return get_term_meta( (int) substr( $id, 5 ), $key, true );
	}
	return get_post_meta( (int) $id, $key, true );
}

/**
 * カスタマイザー設定値
 */
function tk_opt( string $key ): string {
	$defaults = array(
		'tk_tel'          => '0120-000-000',
		'tk_tel_hours'    => '受付 8:00〜20:00／年中無休',
		'tk_company'      => '株式会社テンポスバスターズ（東証上場グループ）',
		'tk_license'      => '東京都公安委員会 第000000000000号',
		'tk_cf7_agri'     => '',
		'tk_cf7_tool'     => '',
		'tk_cf7_contact'  => '',
	) + tk_africa_defaults();
	return (string) get_theme_mod( $key, $defaults[ $key ] ?? '' );
}

/**
 * 農機具 LP「アフリカで再活用」訴求の既定値（外観 → カスタマイズ → 農機具LP：アフリカ訴求）
 *
 * 文言は事実（買取品をアフリカへ輸出し現地で再活用）の範囲にとどめる。
 * 国名・台数などの具体的な数字は、根拠資料がそろうまで載せない。
 */
function tk_africa_defaults(): array {
	return array(
		'tk_africa_enable' => '1',
		'tk_africa_badge'  => '買い取った農機具は、アフリカの農業で再び活躍します',
		'tk_africa_title'  => "あなたの農機具を、\nアフリカの農業の力に。",
		'tk_africa_lead'   => 'テンポスが買い取った農機具は、整備したうえでアフリカへ輸出され、現地の農業の現場で再び使われます。使わなくなった農機具を、処分ではなく次の担い手へ。',
		'tk_africa_image'  => '',
	);
}

/**
 * 「アフリカで再活用」訴求を表示するか
 */
function tk_africa_enabled(): bool {
	return '1' === tk_opt( 'tk_africa_enable' );
}

/**
 * tel: リンク用の番号
 */
function tk_tel_href(): string {
	return 'tel:' . preg_replace( '/[^0-9+]/', '', tk_opt( 'tk_tel' ) );
}

/**
 * SVG アイコン（スプライト参照）
 */
function tk_icon( string $name, string $class = 'icon' ): string {
	return sprintf( '<svg class="%s" aria-hidden="true"><use href="#i-%s"/></svg>', esc_attr( $class ), esc_attr( $name ) );
}

/**
 * 現在のページのトーン：hub / agri / tool
 */
function tk_theme_tone(): string {
	static $tone = null;
	if ( null !== $tone ) {
		return $tone;
	}
	$tone = 'hub';

	if ( is_page() ) {
		$page = get_queried_object();
		$slug = $page->post_name ?? '';
		$root = $page->post_parent ? get_post_field( 'post_name', get_post_ancestors( $page )[ count( get_post_ancestors( $page ) ) - 1 ] ) : $slug;
		if ( 'agricultural-equipment' === $root ) {
			$tone = 'agri';
		} elseif ( 'tool' === $root ) {
			$tone = 'tool';
		}
	} elseif ( is_tax( 'genre' ) ) {
		$tone = tk_genre_tone( get_queried_object()->slug );
	} elseif ( is_singular( array( 'result', 'column' ) ) ) {
		$tone = tk_post_tone( get_queried_object_id() );
	} elseif ( is_tax( 'series' ) ) {
		$tone = tk_genre_tone( (string) tk_field( 'series_genre', 'term_' . get_queried_object_id() ) );
	}
	return $tone;
}

/**
 * ジャンル slug → トーン
 */
function tk_genre_tone( string $slug ): string {
	return in_array( $slug, array( 'agri', 'tool' ), true ) ? $slug : 'hub';
}

/**
 * 投稿のジャンル（genre タクソノミー → 連載のジャンル の順）
 */
function tk_post_genre( int $post_id ): string {
	$terms = get_the_terms( $post_id, 'genre' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		return $terms[0]->slug;
	}
	$series = get_the_terms( $post_id, 'series' );
	if ( $series && ! is_wp_error( $series ) ) {
		return (string) tk_field( 'series_genre', 'term_' . $series[0]->term_id );
	}
	return '';
}

function tk_post_tone( int $post_id ): string {
	return tk_genre_tone( tk_post_genre( $post_id ) );
}

/**
 * ジャンル表示名
 */
function tk_genre_label( string $slug ): string {
	$labels = array(
		'agri' => '農機具',
		'tool' => '工具',
		'deal' => '買取・手続き',
	);
	return $labels[ $slug ] ?? '';
}

/**
 * LP ページの URL（slug 指定）
 */
function tk_page_url( string $path ): string {
	$page = get_page_by_path( $path );
	return $page ? get_permalink( $page ) : home_url( '/' . trim( $path, '/' ) . '/' );
}

/**
 * 固定ページが公開済みか
 */
function tk_page_published( string $path ): bool {
	$page = get_page_by_path( $path );
	return $page && 'publish' === $page->post_status;
}

/**
 * ジャンル別の査定フォーム位置
 */
function tk_form_url( string $genre ): string {
	if ( 'agri' === $genre ) {
		return tk_page_url( 'agricultural-equipment' ) . '#form';
	}
	if ( 'tool' === $genre ) {
		return tk_page_url( 'tool' ) . '#form';
	}
	return home_url( '/#select' );
}

/**
 * ひらがな読み → 五十音の行
 */
function tk_kana_row( string $yomi ): string {
	$first = mb_substr( mb_convert_kana( trim( $yomi ), 'c' ), 0, 1 );
	$rows  = array(
		'あ' => 'あいうえおぁぃぅぇぉゔ',
		'か' => 'かきくけこがぎぐげご',
		'さ' => 'さしすせそざじずぜぞ',
		'た' => 'たちつてとだぢづでどっ',
		'な' => 'なにぬねの',
		'は' => 'はひふへほばびぶべぼぱぴぷぺぽ',
		'ま' => 'まみむめも',
		'や' => 'やゆよゃゅょ',
		'ら' => 'らりるれろ',
		'わ' => 'わをんゎ',
	);
	foreach ( $rows as $row => $chars ) {
		if ( '' !== $first && str_contains( $chars, $first ) ) {
			return $row;
		}
	}
	return '他';
}

/**
 * 連載の各回（公開済み＋予約投稿）を回数順で取得
 *
 * @return WP_Post[]
 */
function tk_series_episodes( int $term_id ): array {
	$posts = get_posts(
		array(
			'post_type'      => 'column',
			'post_status'    => array( 'publish', 'future' ),
			'posts_per_page' => -1,
			'tax_query'      => array(
				array(
					'taxonomy' => 'series',
					'terms'    => $term_id,
				),
			),
			'orderby'        => 'date',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	);
	usort(
		$posts,
		function ( $a, $b ) {
			$ea = (int) tk_field( 'col_episode', $a->ID );
			$eb = (int) tk_field( 'col_episode', $b->ID );
			return ( $ea ?: PHP_INT_MAX ) <=> ( $eb ?: PHP_INT_MAX ) ?: strcmp( $a->post_date, $b->post_date );
		}
	);
	return $posts;
}

/**
 * 連載の総回数（設定値 > 実際の本数）
 */
function tk_series_total( int $term_id, int $count ): int {
	return max( (int) tk_field( 'series_total', 'term_' . $term_id ), $count );
}

/**
 * 連載の路線図（横）
 *
 * @param WP_Term $term 連載.
 * @param int     $here 現在の回.
 */
function tk_route_h( WP_Term $term, int $here = 0 ): string {
	$eps   = tk_series_episodes( $term->term_id );
	$total = tk_series_total( $term->term_id, count( $eps ) );
	$pub   = count( array_filter( $eps, fn( $p ) => 'publish' === $p->post_status ) );
	$f     = $total > 1 ? max( 0, $pub - 1 ) / ( $total - 1 ) : 0;

	$out = sprintf( '<ol class="route" data-series="%s" style="--f:%.2f"><span class="route__fill" aria-hidden="true"></span>', esc_attr( $term->slug ), $f );
	for ( $i = 1; $i <= $total; $i++ ) {
		$p      = $eps[ $i - 1 ] ?? null;
		$is_pub = $p && 'publish' === $p->post_status;
		$cls    = trim( ( $is_pub ? 'is-pub' : '' ) . ( $here === $i ? ' is-here' : '' ) );
		$lbl    = $is_pub ? sprintf( '第%d回', $i ) : ( $p ? get_the_date( 'm.d', $p ) . '公開' : '準備中' );
		$dot    = sprintf( '<span class="dot">%02d</span><span class="lbl">%s</span>', $i, esc_html( $lbl ) );
		$inner  = $is_pub
			? sprintf( '<a href="%s" aria-label="%s">%s</a>', esc_url( get_permalink( $p ) ), esc_attr( sprintf( '第%d回 %s', $i, get_the_title( $p ) ) ), $dot )
			: '<span class="st">' . $dot . '</span>';
		$out   .= sprintf( '<li class="%s" data-ep="%d">%s</li>', esc_attr( $cls ), $i, $inner );
	}
	return $out . '</ol>';
}

/**
 * 連載の路線図（縦・各回のカード）
 */
function tk_route_v( WP_Term $term ): string {
	$eps   = tk_series_episodes( $term->term_id );
	$total = tk_series_total( $term->term_id, count( $eps ) );
	$out   = sprintf( '<ol class="route-v" data-series="%s">', esc_attr( $term->slug ) );
	for ( $i = 1; $i <= $total; $i++ ) {
		$p      = $eps[ $i - 1 ] ?? null;
		$is_pub = $p && 'publish' === $p->post_status;
		$badge  = $is_pub
			? '<span class="st-badge st-badge--pub">公開中</span>'
			: sprintf( '<span class="st-badge">%s</span>', $p ? esc_html( get_the_date( 'Y.m.d', $p ) . ' 公開予定' ) : '準備中' );
		$title  = $p ? get_the_title( $p ) : '準備中';
		$desc   = $p ? ( has_excerpt( $p ) ? get_the_excerpt( $p ) : '' ) : '';
		$body   = sprintf(
			'<span class="ep__meta">第%d回 %s<span class="st-badge st-badge--read" data-read-badge hidden>既読</span></span><span class="ep__title">%s</span>%s',
			$i,
			$badge,
			esc_html( $title ),
			$desc ? '<span class="ep__desc">' . esc_html( $desc ) . '</span>' : ''
		);
		$ep     = $is_pub
			? sprintf( '<a class="ep" href="%s">%s</a>', esc_url( get_permalink( $p ) ), $body )
			: '<div class="ep">' . $body . '</div>';
		$out   .= sprintf( '<li class="%s" data-ep="%d"><span class="dot">%02d</span>%s</li>', $is_pub ? 'is-pub' : '', $i, $i, $ep );
	}
	return $out . '</ol>';
}

/**
 * 投稿の連載（最初の1件）
 */
function tk_post_series( int $post_id ): ?WP_Term {
	$terms = get_the_terms( $post_id, 'series' );
	return ( $terms && ! is_wp_error( $terms ) ) ? $terms[0] : null;
}

/**
 * 写真プレースホルダー or アイキャッチ
 */
function tk_media( ?int $post_id, string $size, string $label, string $bg = '', string $extra_class = '' ): string {
	if ( $post_id && has_post_thumbnail( $post_id ) ) {
		return sprintf(
			'<div class="ph ph--img %s">%s</div>',
			esc_attr( $extra_class ),
			get_the_post_thumbnail( $post_id, $size, array( 'alt' => esc_attr( get_the_title( $post_id ) ) ) )
		);
	}
	return sprintf(
		'<div class="ph %s" data-label="%s"%s></div>',
		esc_attr( $extra_class ),
		esc_attr( $label ),
		$bg ? ' style="--ph-bg:' . esc_attr( $bg ) . '"' : ''
	);
}

/**
 * パンくず
 *
 * @param array<int, array{0:string,1?:string}> $items [ラベル, URL] の配列（最後はURLなし）.
 */
function tk_breadcrumb( array $items ): void {
	array_unshift( $items, array( 'トップ', home_url( '/' ) ) );
	echo '<ol class="breadcrumb">';
	foreach ( $items as $it ) {
		if ( ! empty( $it[1] ) ) {
			printf( '<li><a href="%s">%s</a></li>', esc_url( $it[1] ), esc_html( $it[0] ) );
		} else {
			printf( '<li aria-current="page">%s</li>', esc_html( $it[0] ) );
		}
	}
	echo '</ol>';
}

/**
 * ページ固有のフローティング Dock / シート設定（テンプレートで上書き）
 */
function tk_dock_config( ?array $set = null ): array {
	static $config = array();
	if ( null !== $set ) {
		$config = array_merge( $config, $set );
	}
	return $config;
}

/**
 * コラム本文の整形
 * - h2 に id・data-nav・「SECTION 01」ラベルを付与（目次・Dock 用）
 * - 3つ目の見出しの前に査定 CTA を挿入
 *
 * @return array{0:string,1:array<string,string>} [本文HTML, 目次 [id => 見出し]]
 */
function tk_prepare_column_content( string $html, string $genre ): array {
	$toc = array();
	$n   = 0;
	$html = preg_replace_callback(
		'#<h2([^>]*)>(.*?)</h2>#s',
		function ( $m ) use ( &$toc, &$n ) {
			++$n;
			$text = trim( wp_strip_all_tags( $m[2] ) );
			$id   = preg_match( '/\bid="([^"]+)"/', $m[1], $idm ) ? $idm[1] : 'sec-' . $n;
			$toc[ $id ] = $text;
			$attrs = preg_replace( '/\bid="[^"]*"/', '', $m[1] );
			return sprintf( '<h2 id="%s" data-nav="%s"%s><span class="n">SECTION %02d</span>%s</h2>', esc_attr( $id ), esc_attr( $text ), $attrs, $n, $m[2] );
		},
		$html
	);

	if ( $n >= 4 ) {
		$cta  = sprintf(
			'<aside class="inline-cta"><p>まずは無料査定で、いくらになるか確認<small>写真を添付して1分で依頼できます。出張費・査定料・キャンセル料0円。</small></p><a class="btn" href="%s">%s無料査定フォームへ</a></aside>',
			esc_url( tk_form_url( $genre ) ),
			tk_icon( 'edit' )
		);
		$pos  = 0;
		for ( $i = 0; $i < 3; $i++ ) {
			$pos = strpos( $html, '<h2 ', $pos ) + 1;
		}
		$html = substr_replace( $html, $cta, $pos - 1, 0 );
	}
	return array( $html, $toc );
}
