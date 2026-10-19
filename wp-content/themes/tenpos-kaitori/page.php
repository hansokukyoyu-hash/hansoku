<?php
/**
 * 固定ページ（対応エリア・お問い合わせ・規約など）
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

get_header();
while ( have_posts() ) :
	the_post();
	?>
	<main id="main">
		<section class="page-hero">
			<div class="container">
				<?php tk_breadcrumb( array( array( get_the_title() ) ) ); ?>
				<h1 class="page-hero__title"><?php the_title(); ?></h1>
				<?php if ( has_excerpt() ) : ?>
					<p class="page-hero__lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php endif; ?>
			</div>
		</section>
		<div class="container page-body">
			<div class="prose">
				<?php the_content(); ?>
			</div>
			<?php if ( 'contact' === get_post_field( 'post_name' ) ) : ?>
				<div class="form-card form-card--page">
					<h2>お問い合わせ・無料査定フォーム</h2>
					<?php tk_render_cf7( 'tk_cf7_contact' ); ?>
					<?php get_template_part( 'parts/form-done', null, array( 'type' => 'contact' ) ); ?>
					<p class="micro"><span>査定料・キャンセル料0円</span><span>強引な営業は一切ありません</span></p>
				</div>
			<?php endif; ?>
		</div>
	</main>
	<?php
endwhile;
get_footer();
