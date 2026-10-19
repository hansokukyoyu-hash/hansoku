<?php
/**
 * サンプルコンテンツ投入（inc/sample-data.json）
 *
 * 既に同じ slug の投稿・タームがある場合はスキップします。
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

/**
 * フィールド保存（ACF があれば update_field、なければ meta）
 *
 * @param mixed $value 値.
 */
function tk_set_field( string $key, $value, $id ): void {
	if ( function_exists( 'update_field' ) ) {
		update_field( $key, $value, $id );
		return;
	}
	if ( is_string( $id ) && str_starts_with( $id, 'term_' ) ) {
		update_term_meta( (int) substr( $id, 5 ), $key, $value );
		return;
	}
	update_post_meta( (int) $id, $key, $value );
}

function tk_sample_post_exists( string $slug, string $type ): ?int {
	$found = get_posts(
		array(
			'post_type'      => $type,
			'name'           => $slug,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);
	return $found ? (int) $found[0] : null;
}

/**
 * @return string[] 実行ログ
 */
function tk_import_samples(): array {
	$data = json_decode( (string) file_get_contents( TK_DIR . '/inc/sample-data.json' ), true );
	if ( ! $data ) {
		return array( 'サンプルデータを読み込めませんでした' );
	}
	tk_ensure_genres();
	$log   = array();
	$count = array_fill_keys( array( 'glossary', 'series', 'column', 'faq', 'result' ), 0 );

	// 用語集.
	$gl_ids = array();
	foreach ( $data['glossary'] as $g ) {
		$id = tk_sample_post_exists( $g['slug'], 'glossary' );
		if ( ! $id ) {
			$id = wp_insert_post(
				array(
					'post_type'    => 'glossary',
					'post_status'  => 'publish',
					'post_title'   => $g['title'],
					'post_name'    => $g['slug'],
					'post_content' => $g['content'],
				)
			);
			if ( ! $id || is_wp_error( $id ) ) {
				continue;
			}
			wp_set_object_terms( $id, $g['genre'], 'genre' );
			tk_set_field( 'gl_yomi', $g['yomi'], $id );
			if ( $g['link'] ) {
				tk_set_field(
					'gl_link',
					array(
						'url'    => home_url( $g['link']['url'] ),
						'title'  => $g['link']['title'],
						'target' => '',
					),
					$id
				);
			}
			++$count['glossary'];
			$gl_ids[ $g['slug'] ] = array( (int) $id, $g['related'] );
		}
	}
	$all_gl = array();
	foreach ( $data['glossary'] as $g ) {
		$all_gl[ $g['slug'] ] = tk_sample_post_exists( $g['slug'], 'glossary' );
	}
	foreach ( $gl_ids as [ $id, $related ] ) {
		tk_set_field( 'gl_related', array_values( array_filter( array_map( fn( $s ) => $all_gl[ $s ] ?? null, $related ) ) ), $id );
	}

	// 連載.
	foreach ( $data['series'] as $s ) {
		if ( term_exists( $s['slug'], 'series' ) ) {
			continue;
		}
		$t = wp_insert_term( $s['name'], 'series', array( 'slug' => $s['slug'] ) );
		if ( is_wp_error( $t ) ) {
			continue;
		}
		$tid = 'term_' . $t['term_id'];
		tk_set_field( 'series_total', $s['total'], $tid );
		tk_set_field( 'series_genre', $s['genre'], $tid );
		tk_set_field( 'series_lead', $s['lead'], $tid );
		tk_set_field( 'series_schedule', $s['schedule'], $tid );
		++$count['series'];
	}

	// コラム（未来日付は予約投稿になる）.
	foreach ( $data['columns'] as $c ) {
		if ( tk_sample_post_exists( $c['slug'], 'column' ) ) {
			continue;
		}
		$date   = $c['date'];
		$future = strtotime( $date ) > current_time( 'timestamp' );
		$id     = wp_insert_post(
			array(
				'post_type'     => 'column',
				'post_status'   => $future ? 'future' : 'publish',
				'post_title'    => $c['title'],
				'post_name'     => $c['slug'],
				'post_excerpt'  => $c['excerpt'],
				'post_content'  => $c['content'],
				'post_date'     => $date,
				'post_date_gmt' => get_gmt_from_date( $date ),
			)
		);
		if ( ! $id || is_wp_error( $id ) ) {
			continue;
		}
		wp_set_object_terms( $id, $c['series'], 'series' );
		tk_set_field( 'col_episode', $c['ep'], $id );
		tk_set_field( 'col_read_min', $c['read'], $id );
		tk_set_field( 'col_conclusion', $c['conclusion'], $id );
		tk_set_field( 'col_points', implode( "\n", $c['points'] ), $id );
		++$count['column'];
	}

	// FAQ.
	foreach ( $data['faqs'] as $i => $f ) {
		$exists = get_posts(
			array(
				'post_type'      => 'faq',
				'title'          => $f['q'],
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( $exists ) {
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'faq',
				'post_status'  => 'publish',
				'post_title'   => $f['q'],
				'post_content' => $f['a'],
				'menu_order'   => $i,
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			wp_set_object_terms( $id, $f['genre'], 'genre' );
			++$count['faq'];
		}
	}

	// 買取実績.
	foreach ( $data['results'] as $r ) {
		$slug = sanitize_title( $r['genre'] . '-' . $r['model'] );
		if ( tk_sample_post_exists( $slug, 'result' ) ) {
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_type'   => 'result',
				'post_status' => 'publish',
				'post_title'  => $r['title'],
				'post_name'   => $slug,
				'post_date'   => $r['date'] . ' 10:00:00',
			)
		);
		if ( ! $id || is_wp_error( $id ) ) {
			continue;
		}
		wp_set_object_terms( $id, $r['genre'], 'genre' );
		foreach ( array( 'maker', 'model', 'year', 'condition', 'area', 'price' ) as $k ) {
			tk_set_field( 'rs_' . $k, $r[ $k ], $id );
		}
		++$count['result'];
	}

	$labels = array(
		'glossary' => '用語',
		'series'   => '連載',
		'column'   => 'コラム',
		'faq'      => 'FAQ',
		'result'   => '買取実績',
	);
	foreach ( $count as $k => $n ) {
		$log[] = sprintf( 'サンプル%sを %d 件追加', $labels[ $k ], $n );
	}
	return $log;
}
