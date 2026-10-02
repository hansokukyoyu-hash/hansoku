<?php
/**
 * 汎用一覧（投稿・検索など）
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main">
	<section class="page-hero">
		<div class="container">
			<?php tk_breadcrumb( array( array( wp_strip_all_tags( get_the_archive_title() ?: 'お知らせ' ) ) ) ); ?>
			<h1 class="page-hero__title"><?php echo esc_html( is_search() ? '「' . get_search_query() . '」の検索結果' : wp_strip_all_tags( get_the_archive_title() ?: 'お知らせ' ) ); ?></h1>
		</div>
	</section>
	<section class="section section--tight">
		<div class="container">
			<?php if ( have_posts() ) : ?>
				<div class="post-grid">
					<?php
					while ( have_posts() ) {
						the_post();
						get_template_part( 'parts/post-card' );
					}
					?>
				</div>
				<?php the_posts_pagination( array( 'prev_text' => '前へ', 'next_text' => '次へ' ) ); ?>
			<?php else : ?>
				<p class="empty-note">該当するページがありません。</p>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php
get_footer();
