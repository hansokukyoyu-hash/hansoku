<?php
/**
 * 構造化データ（JSON-LD）
 *
 * - トップ        : Organization
 * - LP / FAQ一覧  : FAQPage（FAQ 投稿から生成）
 * - 買取実績      : Product + Offer（個別）
 * - コラム        : Article（連載は CreativeWorkSeries）＋ BreadcrumbList
 * - 用語集        : DefinedTermSet
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

/**
 * ジャンル別 FAQ を取得
 *
 * @return WP_Post[]
 */
function tk_get_faqs( string $genre = '', int $limit = -1 ): array {
	$args = array(
		'post_type'      => 'faq',
		'posts_per_page' => $limit,
		'orderby'        => array(
			'menu_order' => 'ASC',
			'date'       => 'ASC',
		),
		'no_found_rows'  => true,
	);
	if ( $genre ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'genre',
				'field'    => 'slug',
				'terms'    => $genre,
			),
		);
	}
	return get_posts( $args );
}

function tk_jsonld_print( array $data ): void {
	echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}

function tk_faq_jsonld( array $faqs ): ?array {
	if ( ! $faqs ) {
		return null;
	}
	return array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => array_map(
			fn( $f ) => array(
				'@type'          => 'Question',
				'name'           => get_the_title( $f ),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => wp_strip_all_tags( strip_shortcodes( $f->post_content ) ),
				),
			),
			$faqs
		),
	);
}

add_action(
	'wp_head',
	function () {
		$org = array(
			'@type' => 'Organization',
			'name'  => '株式会社テンポスバスターズ',
			'url'   => home_url( '/' ),
			'logo'  => TK_URI . '/assets/img/logo_tepos_dr-tenpos.png',
		);

		if ( is_front_page() ) {
			tk_jsonld_print( array( '@context' => 'https://schema.org' ) + $org );
			return;
		}

		if ( is_page( array( 'agricultural-equipment', 'tool' ) ) ) {
			$data = tk_faq_jsonld( tk_get_faqs( is_page( 'tool' ) ? 'tool' : 'agri' ) );
			if ( $data ) {
				tk_jsonld_print( $data );
			}
			return;
		}

		if ( is_post_type_archive( 'faq' ) ) {
			$data = tk_faq_jsonld( tk_get_faqs() );
			if ( $data ) {
				tk_jsonld_print( $data );
			}
			return;
		}

		if ( is_singular( 'result' ) ) {
			$id    = get_queried_object_id();
			$price = (int) tk_field( 'rs_price', $id );
			$data  = array(
				'@context' => 'https://schema.org',
				'@type'    => 'Product',
				'name'     => get_the_title( $id ),
				'brand'    => tk_field( 'rs_maker', $id ) ? array( '@type' => 'Brand', 'name' => tk_field( 'rs_maker', $id ) ) : null,
				'model'    => tk_field( 'rs_model', $id ) ?: null,
				'image'    => get_the_post_thumbnail_url( $id, 'large' ) ?: null,
				'offers'   => $price ? array(
					'@type'         => 'Offer',
					'price'         => $price,
					'priceCurrency' => 'JPY',
					'description'   => '買取価格',
					'seller'        => $org,
				) : null,
			);
			tk_jsonld_print( array_filter( $data ) );
			return;
		}

		if ( is_singular( 'column' ) ) {
			$id     = get_queried_object_id();
			$series = tk_post_series( $id );
			$crumbs = array(
				array( 'トップ', home_url( '/' ) ),
				array( 'コラム', get_post_type_archive_link( 'column' ) ),
			);
			if ( $series ) {
				$crumbs[] = array( $series->name, get_term_link( $series ) );
			}
			$crumbs[] = array( get_the_title( $id ), get_permalink( $id ) );

			$article = array(
				'@type'         => 'Article',
				'headline'      => get_the_title( $id ),
				'datePublished' => get_the_date( 'c', $id ),
				'dateModified'  => get_the_modified_date( 'c', $id ),
				'author'        => array( '@type' => 'Organization', 'name' => 'テンポスバスターズ 買取事業部' ),
				'publisher'     => $org,
				'image'         => get_the_post_thumbnail_url( $id, 'large' ) ?: null,
				'description'   => tk_field( 'col_conclusion', $id ) ?: get_the_excerpt( $id ),
			);
			if ( $series ) {
				$article['isPartOf'] = array(
					'@type' => 'CreativeWorkSeries',
					'name'  => $series->name,
					'url'   => get_term_link( $series ),
				);
				$article['position'] = (int) tk_field( 'col_episode', $id ) ?: null;
			}
			tk_jsonld_print(
				array(
					'@context' => 'https://schema.org',
					'@graph'   => array(
						array_filter( $article ),
						array(
							'@type'           => 'BreadcrumbList',
							'itemListElement' => array_map(
								fn( $c, $i ) => array(
									'@type'    => 'ListItem',
									'position' => $i + 1,
									'name'     => $c[0],
									'item'     => $c[1],
								),
								$crumbs,
								array_keys( $crumbs )
							),
						),
					),
				)
			);
			return;
		}

		if ( is_post_type_archive( 'glossary' ) ) {
			global $wp_query;
			tk_jsonld_print(
				array(
					'@context'       => 'https://schema.org',
					'@type'          => 'DefinedTermSet',
					'name'           => '農機具・工具買取 用語集',
					'url'            => get_post_type_archive_link( 'glossary' ),
					'hasDefinedTerm' => array_map(
						fn( $p ) => array(
							'@type'       => 'DefinedTerm',
							'name'        => get_the_title( $p ),
							'termCode'    => $p->post_name,
							'url'         => get_post_type_archive_link( 'glossary' ) . '#' . $p->post_name,
							'description' => wp_strip_all_tags( strip_shortcodes( $p->post_content ) ),
						),
						$wp_query->posts
					),
				)
			);
		}
	},
	5
);
