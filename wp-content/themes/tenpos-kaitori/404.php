<?php
/**
 * 404
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main">
	<section class="page-hero">
		<div class="container">
			<span class="eyebrow">404 Not Found</span>
			<h1 class="page-hero__title">ページが見つかりません</h1>
			<p class="page-hero__lead">お探しのページは移動または削除された可能性があります。</p>
			<div class="cat-chips" style="margin-top:24px">
				<a class="chip-link" href="<?php echo esc_url( home_url( '/' ) ); ?>">トップへ</a>
				<a class="chip-link" href="<?php echo esc_url( tk_page_url( 'agricultural-equipment' ) ); ?>">農機具買取</a>
				<a class="chip-link" href="<?php echo esc_url( tk_page_url( 'tool' ) ); ?>">工具買取</a>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
