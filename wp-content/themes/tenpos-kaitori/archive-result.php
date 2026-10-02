<?php
/**
 * 買取実績一覧（/results/・/agricultural-equipment/results/・/tool/results/）
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

$tk_genre = (string) get_query_var( 'genre' );
$tk_label = tk_genre_label( $tk_genre );
$tk_title = $tk_label ? $tk_label . 'の買取実績' : '買取実績';

get_header();
?>
<main id="main">
	<section class="page-hero">
		<div class="container">
			<?php
			$tk_crumbs = array();
			if ( in_array( $tk_genre, array( 'agri', 'tool' ), true ) ) {
				$tk_crumbs[] = array( 'agri' === $tk_genre ? '農機具買取' : '工具買取', tk_page_url( 'agri' === $tk_genre ? 'agricultural-equipment' : 'tool' ) );
			}
			$tk_crumbs[] = array( $tk_title );
			tk_breadcrumb( $tk_crumbs );
			?>
			<span class="eyebrow">Results</span>
			<h1 class="page-hero__title"><?php echo esc_html( $tk_title ); ?></h1>
			<p class="page-hero__lead">車種・メーカー・状態・買取地域・買取日を掲載しています。動かない・古い品にも買取価格がついています。</p>
			<div class="cat-chips" style="margin:24px 0 0">
				<a class="chip-link<?php echo '' === $tk_genre ? ' is-current' : ''; ?>" href="<?php echo esc_url( tk_results_url() ); ?>">すべて</a>
				<a class="chip-link<?php echo 'agri' === $tk_genre ? ' is-current' : ''; ?>" href="<?php echo esc_url( tk_results_url( 'agri' ) ); ?>">農機具</a>
				<a class="chip-link<?php echo 'tool' === $tk_genre ? ' is-current' : ''; ?>" href="<?php echo esc_url( tk_results_url( 'tool' ) ); ?>">工具</a>
			</div>
		</div>
	</section>

	<section class="section section--tight" id="results" data-nav="<?php echo esc_attr( $tk_title ); ?>">
		<div class="container">
			<?php if ( have_posts() ) : ?>
				<div class="results-grid">
					<?php
					$tk_i = 0;
					while ( have_posts() ) {
						the_post();
						get_template_part( 'parts/result-card', null, array( 'i' => $tk_i++ ) );
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
				<p class="empty-note">買取実績は準備中です。</p>
			<?php endif; ?>
		</div>
	</section>
</main>
<?php
get_footer();
