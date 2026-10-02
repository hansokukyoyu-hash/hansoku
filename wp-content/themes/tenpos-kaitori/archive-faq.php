<?php
/**
 * よくある質問（/faq/）
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

$tk_sections = array(
	'agri' => '農機具の買取について',
	'tool' => '工具の買取について',
	'deal' => '査定・手続きについて',
);

get_header();
get_template_part(
	'parts/anchor-tabs',
	null,
	array(
		'items' => array_combine(
			array_map( fn( $k ) => 'faq-' . $k, array_keys( $tk_sections ) ),
			array( '農機具', '工具', '査定・手続き' )
		),
	)
);
?>
<main id="main">
	<section class="page-hero">
		<div class="container">
			<?php tk_breadcrumb( array( array( 'よくある質問' ) ) ); ?>
			<span class="eyebrow">FAQ</span>
			<h1 class="page-hero__title">よくある質問</h1>
			<p class="page-hero__lead">出張費・査定料・キャンセル料はすべて0円です。そのほかのご質問をジャンル別にまとめました。</p>
		</div>
	</section>
	<?php foreach ( $tk_sections as $tk_genre => $tk_title ) : ?>
		<section class="section section--tight" id="faq-<?php echo esc_attr( $tk_genre ); ?>" data-nav="<?php echo esc_attr( $tk_title ); ?>">
			<div class="container">
				<div class="sec-head"><h2 class="sec-title" style="font-size:clamp(22px,3vw,30px)"><?php echo esc_html( $tk_title ); ?></h2></div>
				<?php get_template_part( 'parts/faq-list', null, array( 'genre' => $tk_genre ) ); ?>
			</div>
		</section>
	<?php endforeach; ?>
</main>
<?php
get_footer();
