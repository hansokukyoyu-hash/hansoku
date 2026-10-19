<?php
/**
 * コラム一覧（/column/）
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

tk_dock_config(
	array(
		'sheet_title'   => 'COLUMN',
		'switch_href'   => get_post_type_archive_link( 'glossary' ),
		'switch_small'  => '買取でよく出てくることば',
		'switch_strong' => '用語集を見る',
		'switch2_href'  => '',
	)
);
$tk_series = get_terms(
	array(
		'taxonomy'   => 'series',
		'hide_empty' => false,
	)
);

get_header();
?>
<main id="main">
	<section class="page-hero">
		<div class="container">
			<?php tk_breadcrumb( array( array( 'コラム' ) ) ); ?>
			<span class="eyebrow">Column</span>
			<h1 class="page-hero__title">お役立ちコラム</h1>
			<p class="page-hero__lead">農機具・工具を「損せず・手間なく」手放すための知識を、テーマごとの連載でお届けします。</p>
		</div>
	</section>

	<?php if ( $tk_series && ! is_wp_error( $tk_series ) && ! is_paged() ) : ?>
		<section class="section section--tight" id="series" data-nav="連載シリーズ">
			<div class="container">
				<div class="sec-head reveal"><span class="eyebrow">Series</span><h2 class="sec-title">連載シリーズ</h2></div>
				<div class="series-list">
					<?php
					foreach ( $tk_series as $tk_term ) :
						$tk_eps   = tk_series_episodes( $tk_term->term_id );
						$tk_total = tk_series_total( $tk_term->term_id, count( $tk_eps ) );
						$tk_pub   = count( array_filter( $tk_eps, fn( $p ) => 'publish' === $p->post_status ) );
						$tk_next  = current( array_filter( $tk_eps, fn( $p ) => 'future' === $p->post_status ) );
						$tk_genre = (string) tk_field( 'series_genre', 'term_' . $tk_term->term_id );
						?>
						<article class="series-card series-card--<?php echo esc_attr( 'tool' === $tk_genre ? 'tool' : 'agri' ); ?> reveal">
							<span class="series-card__no" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $tk_total ) ); ?></span>
							<span class="series-card__tag">連載｜全<?php echo esc_html( $tk_total ); ?>回</span>
							<h3><?php echo esc_html( $tk_term->name ); ?></h3>
							<p><?php echo esc_html( (string) tk_field( 'series_lead', 'term_' . $tk_term->term_id ) ?: $tk_term->description ); ?></p>
							<?php echo tk_route_h( $tk_term ); // phpcs:ignore ?>
							<div class="series-card__foot">
								<span class="series-card__count"><b><?php echo esc_html( $tk_pub ); ?></b> / <?php echo esc_html( $tk_total ); ?> 回 公開中</span>
								<?php if ( $tk_next ) : ?>
									<span class="series-card__count">次回 第<?php echo esc_html( (int) tk_field( 'col_episode', $tk_next->ID ) ); ?>回は <b style="font-size:15px"><?php echo esc_html( get_the_date( 'm.d', $tk_next ) ); ?></b> 公開予定</span>
								<?php endif; ?>
							</div>
							<a class="btn" href="<?php echo esc_url( get_term_link( $tk_term ) ); ?>">連載を読む<?php echo tk_icon( 'arrow', 'icon arrow' ); // phpcs:ignore ?></a>
						</article>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<section class="section section--tight" id="latest" data-nav="新着記事" style="background:var(--surface)">
		<div class="container">
			<div class="sec-head reveal"><span class="eyebrow">Latest</span><h2 class="sec-title">新着記事</h2></div>
			<div class="cat-chips" data-filter="#latest .post-card" role="group" aria-label="カテゴリで絞り込み">
				<button type="button" data-value="all" aria-pressed="true">すべて</button>
				<button type="button" data-value="agri" aria-pressed="false">農機具</button>
				<button type="button" data-value="tool" aria-pressed="false">工具</button>
				<button type="button" data-value="deal" aria-pressed="false">買取・手続き</button>
			</div>
			<?php if ( have_posts() ) : ?>
				<div class="post-grid">
					<?php
					while ( have_posts() ) {
						the_post();
						get_template_part( 'parts/post-card' );
					}
					?>
				</div>
				<?php
				the_posts_pagination(
					array(
						'mid_size'  => 1,
						'prev_text' => '前へ',
						'next_text' => '次へ',
					)
				);
				?>
			<?php else : ?>
				<p class="empty-note">記事は準備中です。</p>
			<?php endif; ?>
		</div>
	</section>

	<section class="section section--tight" id="glossary-link" data-nav="用語集">
		<div class="container">
			<a class="series-card reveal" href="<?php echo esc_url( get_post_type_archive_link( 'glossary' ) ); ?>" style="--series:var(--ink)">
				<span class="series-card__tag">Glossary</span>
				<h3>わからない言葉は「用語集」で</h3>
				<p>アワーメーター、名義変更、ジャンク品……買取でよく出てくることばを、ひとことで解説しています。</p>
				<span class="btn" style="justify-self:start">用語集を見る<?php echo tk_icon( 'arrow', 'icon arrow' ); // phpcs:ignore ?></span>
			</a>
		</div>
	</section>
</main>
<?php
get_footer();
