<?php
/**
 * 連載トップ（/column/series/{slug}/）
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

$tk_term  = get_queried_object();
$tk_tid   = 'term_' . $tk_term->term_id;
$tk_eps   = tk_series_episodes( $tk_term->term_id );
$tk_total = tk_series_total( $tk_term->term_id, count( $tk_eps ) );
$tk_pubs  = array_values( array_filter( $tk_eps, fn( $p ) => 'publish' === $p->post_status ) );
$tk_genre = (string) tk_field( 'series_genre', $tk_tid );
$tk_lead  = (string) tk_field( 'series_lead', $tk_tid ) ?: $tk_term->description;

tk_dock_config(
	array(
		'sheet_title'   => 'SERIES',
		'pct_label'     => '現在',
		'switch_href'   => get_post_type_archive_link( 'column' ),
		'switch_small'  => 'ほかの連載・記事',
		'switch_strong' => 'コラム一覧へ',
		'switch2_href'  => '',
	)
);

get_header();
?>
<main id="main">
	<section class="page-hero">
		<div class="container">
			<?php tk_breadcrumb( array( array( 'コラム', get_post_type_archive_link( 'column' ) ), array( $tk_term->name ) ) ); ?>
			<span class="eyebrow">Series ｜ 全<?php echo esc_html( $tk_total ); ?>回</span>
			<h1 class="page-hero__title"><?php echo esc_html( $tk_term->name ); ?></h1>
			<?php if ( $tk_lead ) : ?>
				<p class="page-hero__lead"><?php echo esc_html( $tk_lead ); ?></p>
			<?php endif; ?>
			<p class="series-card__count" style="margin-top:18px"><b><?php echo esc_html( count( $tk_pubs ) ); ?></b> / <?php echo esc_html( $tk_total ); ?> 回 公開中<?php echo tk_field( 'series_schedule', $tk_tid ) ? ' ・ ' . esc_html( tk_field( 'series_schedule', $tk_tid ) ) : ''; ?></p>
			<?php if ( $tk_pubs ) : ?>
				<a class="btn btn--primary" href="<?php echo esc_url( get_permalink( $tk_pubs[0] ) ); ?>" data-resume style="margin-top:20px">第1回から読む<?php echo tk_icon( 'arrow', 'icon arrow' ); // phpcs:ignore ?></a>
			<?php endif; ?>
		</div>
	</section>

	<section class="section section--tight" id="episodes" data-nav="連載の各回">
		<div class="container" style="max-width:820px">
			<div class="sec-head reveal"><span class="eyebrow">Episodes</span><h2 class="sec-title">連載の各回</h2></div>
			<?php echo tk_route_v( $tk_term ); // phpcs:ignore ?>
		</div>
	</section>

	<section class="section section--tight" id="cta" data-nav="無料査定" style="background:var(--surface)">
		<div class="container" style="max-width:820px">
			<div class="inline-cta" style="margin:0">
				<p><?php echo esc_html( ( tk_genre_label( $tk_genre ) ?: '農機具・工具' ) . 'の整理、まずは無料査定から' ); ?><small>動かない・古い品も対象です。出張費・査定料・キャンセル料0円。</small></p>
				<a class="btn" href="<?php echo esc_url( tk_form_url( $tk_genre ) ); ?>"><?php echo tk_icon( 'edit' ); // phpcs:ignore ?>無料査定フォームへ</a>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
