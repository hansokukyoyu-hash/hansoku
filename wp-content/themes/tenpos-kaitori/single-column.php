<?php
/**
 * コラム記事（連載対応）
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

the_post();
$tk_id     = get_the_ID();
$tk_genre  = tk_post_genre( $tk_id );
$tk_series = tk_post_series( $tk_id );
$tk_ep     = (int) tk_field( 'col_episode', $tk_id );
[ $tk_body, $tk_toc ] = tk_prepare_column_content( apply_filters( 'the_content', get_the_content() ), $tk_genre );

$tk_prev = $tk_next = null;
$tk_total = 0;
if ( $tk_series ) {
	$tk_eps   = tk_series_episodes( $tk_series->term_id );
	$tk_total = tk_series_total( $tk_series->term_id, count( $tk_eps ) );
	$tk_idx   = array_search( $tk_id, wp_list_pluck( $tk_eps, 'ID' ), true );
	if ( false !== $tk_idx ) {
		$tk_prev = $tk_eps[ $tk_idx - 1 ] ?? null;
		$tk_next = $tk_eps[ $tk_idx + 1 ] ?? null;
	}
	tk_dock_config(
		array(
			'switch_href'   => get_term_link( $tk_series ),
			'switch_small'  => '連載 全' . $tk_total . '回',
			'switch_strong' => '連載の各回を見る',
			'switch2_href'  => '',
		)
	);
} else {
	tk_dock_config(
		array(
			'sheet_title'   => 'INDEX',
			'pct_label'     => '読了',
			'switch_href'   => get_post_type_archive_link( 'column' ),
			'switch_small'  => 'ほかの記事',
			'switch_strong' => 'コラム一覧へ',
			'switch2_href'  => '',
		)
	);
}
$tk_points = array_filter( array_map( 'trim', explode( "\n", (string) tk_field( 'col_points', $tk_id ) ) ) );
$tk_crumbs = array( array( 'コラム', get_post_type_archive_link( 'column' ) ) );
if ( $tk_series ) {
	$tk_crumbs[] = array( $tk_series->name, get_term_link( $tk_series ) );
	$tk_crumbs[] = array( $tk_ep ? "第{$tk_ep}回" : get_the_title() );
} else {
	$tk_crumbs[] = array( get_the_title() );
}

get_header();
?>
<main id="main" class="container">
	<div class="article-wrap">
		<article <?php echo $tk_series ? sprintf( 'data-read-series="%s" data-read-ep="%d"', esc_attr( $tk_series->slug ), (int) $tk_ep ) : ''; ?>>
			<?php tk_breadcrumb( $tk_crumbs ); ?>
			<div class="article-head">
				<?php if ( $tk_series ) : ?>
					<div class="series-banner">
						<div class="series-banner__top"><a href="<?php echo esc_url( get_term_link( $tk_series ) ); ?>">連載｜<?php echo esc_html( $tk_series->name ); ?></a><span class="series-banner__ep">EP.<?php echo esc_html( sprintf( '%02d / %02d', $tk_ep, $tk_total ) ); ?></span></div>
						<?php echo tk_route_h( $tk_series, $tk_ep ); // phpcs:ignore ?>
					</div>
				<?php endif; ?>
				<h1><?php the_title(); ?></h1>
				<div class="article-meta">
					<span>公開：<?php echo esc_html( get_the_date( 'Y.m.d' ) ); ?></span>
					<?php if ( get_the_modified_date( 'Ymd' ) !== get_the_date( 'Ymd' ) ) : ?>
						<span>更新：<?php echo esc_html( get_the_modified_date( 'Y.m.d' ) ); ?></span>
					<?php endif; ?>
					<?php if ( $tk_genre ) : ?>
						<span>カテゴリ：<?php echo esc_html( tk_genre_label( $tk_genre ) ); ?></span>
					<?php endif; ?>
					<?php if ( tk_field( 'col_read_min', $tk_id ) ) : ?>
						<span>読了目安：約<?php echo esc_html( (int) tk_field( 'col_read_min', $tk_id ) ); ?>分</span>
					<?php endif; ?>
				</div>
				<?php echo tk_media( $tk_id, 'tk-wide', 'EYECATCH', 'linear-gradient(135deg,#9ccf8a,#2f6b3a)', 'article-eyecatch' ); // phpcs:ignore ?>
			</div>

			<?php if ( tk_field( 'col_conclusion', $tk_id ) ) : ?>
				<div class="conclusion">
					<span class="conclusion__label">この記事の結論</span>
					<p><?php echo esc_html( tk_field( 'col_conclusion', $tk_id ) ); ?></p>
					<?php if ( $tk_points ) : ?>
						<ul>
							<?php foreach ( $tk_points as $tk_pt ) : ?>
								<li><?php echo esc_html( $tk_pt ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( count( $tk_toc ) >= 2 ) : ?>
				<details class="toc" open><summary>目次</summary><ol>
					<?php foreach ( $tk_toc as $tk_tid => $tk_txt ) : ?>
						<li><a href="#<?php echo esc_attr( $tk_tid ); ?>"><?php echo esc_html( $tk_txt ); ?></a></li>
					<?php endforeach; ?>
				</ol></details>
			<?php endif; ?>

			<div class="prose">
				<?php echo $tk_body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content 済み. ?>
			</div>

			<?php if ( $tk_series ) : ?>
				<nav class="ep-nav" aria-label="連載の前後">
					<?php if ( $tk_prev ) : ?>
						<a href="<?php echo esc_url( get_permalink( $tk_prev ) ); ?>"><small>← 前回 第<?php echo esc_html( (int) tk_field( 'col_episode', $tk_prev->ID ) ); ?>回</small><strong><?php echo esc_html( get_the_title( $tk_prev ) ); ?></strong></a>
					<?php else : ?>
						<a href="<?php echo esc_url( get_term_link( $tk_series ) ); ?>"><small>連載トップ</small><strong>連載の各回を見る</strong></a>
					<?php endif; ?>
					<?php if ( $tk_next && 'publish' === $tk_next->post_status ) : ?>
						<a class="is-next" href="<?php echo esc_url( get_permalink( $tk_next ) ); ?>"><small>次回 第<?php echo esc_html( (int) tk_field( 'col_episode', $tk_next->ID ) ); ?>回 →</small><strong><?php echo esc_html( get_the_title( $tk_next ) ); ?></strong></a>
					<?php elseif ( $tk_next ) : ?>
						<div class="is-soon"><small>次回 第<?php echo esc_html( (int) tk_field( 'col_episode', $tk_next->ID ) ); ?>回｜<?php echo esc_html( get_the_date( 'Y.m.d', $tk_next ) ); ?> 公開予定</small><strong><?php echo esc_html( get_the_title( $tk_next ) ); ?></strong></div>
					<?php elseif ( $tk_ep && $tk_ep < $tk_total ) : ?>
						<div class="is-soon"><small>次回 第<?php echo esc_html( $tk_ep + 1 ); ?>回</small><strong>準備中です</strong></div>
					<?php else : ?>
						<a class="is-next" href="<?php echo esc_url( get_term_link( $tk_series ) ); ?>"><small>連載完結</small><strong>連載の一覧に戻る</strong></a>
					<?php endif; ?>
				</nav>
			<?php endif; ?>

			<div class="author">
				<span class="author__icon">T</span>
				<div><b>テンポスバスターズ 買取事業部</b><p>全国の出張買取で農機具・工具を査定するスタッフが、現場で多いご相談をもとに執筆しています。</p></div>
			</div>
		</article>

		<aside class="article-aside">
			<div class="aside-sticky">
				<?php if ( $tk_series ) : ?>
					<div class="aside-box">
						<h2 class="aside-box__h">連載｜<?php echo esc_html( $tk_series->name ); ?></h2>
						<?php echo tk_route_v( $tk_series ); // phpcs:ignore ?>
					</div>
				<?php endif; ?>
				<div class="aside-box cta">
					<h2 class="aside-box__h"><?php echo esc_html( ( tk_genre_label( $tk_genre ) ?: '農機具・工具' ) . 'の無料査定' ); ?></h2>
					<p>動かない・古い品もOK。出張費・査定料0円。</p>
					<a class="btn" href="<?php echo esc_url( tk_form_url( $tk_genre ) ); ?>"><?php echo tk_icon( 'edit' ); // phpcs:ignore ?>無料査定フォームへ</a>
				</div>
			</div>
		</aside>
	</div>
</main>
<?php
get_footer();
