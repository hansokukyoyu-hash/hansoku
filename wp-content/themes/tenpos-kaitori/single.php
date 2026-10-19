<?php
/**
 * 投稿（お知らせ等）
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

get_header();
while ( have_posts() ) :
	the_post();
	?>
	<main id="main" class="container">
		<div class="article-wrap">
			<article>
				<?php tk_breadcrumb( array( array( get_the_title() ) ) ); ?>
				<div class="article-head">
					<h1><?php the_title(); ?></h1>
					<div class="article-meta"><span>公開：<?php echo esc_html( get_the_date( 'Y.m.d' ) ); ?></span></div>
				</div>
				<div class="prose"><?php the_content(); ?></div>
			</article>
		</div>
	</main>
	<?php
endwhile;
get_footer();
