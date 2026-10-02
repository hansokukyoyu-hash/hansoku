<?php
/**
 * 最新の買取実績（ジャンル指定可）
 *
 * $args: genre（'' = 全ジャンル）, count, filter（bool：稼働/不動フィルター）
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

$tk_genre = $args['genre'] ?? '';
$tk_query = new WP_Query(
	array(
		'post_type'      => 'result',
		'posts_per_page' => $args['count'] ?? 4,
		'no_found_rows'  => true,
		'tax_query'      => $tk_genre ? array(
			array(
				'taxonomy' => 'genre',
				'field'    => 'slug',
				'terms'    => $tk_genre,
			),
		) : array(),
	)
);

if ( ! $tk_query->have_posts() ) {
	echo '<p class="empty-note">買取実績は準備中です。</p>';
	return;
}

if ( ! empty( $args['filter'] ) ) :
	?>
	<div class="filter" data-filter="#results .result-card" role="group" aria-label="状態で絞り込み">
		<button type="button" data-value="all" aria-pressed="true">すべて</button>
		<button type="button" data-value="ok" aria-pressed="false">稼働</button>
		<button type="button" data-value="ng" aria-pressed="false">不動・故障</button>
	</div>
	<?php
endif;

echo '<div class="results-grid">';
$tk_i = 0;
while ( $tk_query->have_posts() ) {
	$tk_query->the_post();
	get_template_part( 'parts/result-card', null, array( 'i' => $tk_i++ ) );
}
echo '</div>';
wp_reset_postdata();
