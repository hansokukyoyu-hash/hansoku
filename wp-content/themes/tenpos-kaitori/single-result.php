<?php
/**
 * 買取実績 詳細
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

get_header();
while ( have_posts() ) :
	the_post();
	$tk_id    = get_the_ID();
	$tk_genre = tk_post_genre( $tk_id );
	$tk_price = (int) tk_field( 'rs_price', $tk_id );
	$tk_conds = array(
		'ok'     => '稼働',
		'ng'     => '不動',
		'broken' => '故障',
		'S'      => 'Sランク（新品・未使用）',
		'A'      => 'Aランク（美品）',
		'B'      => 'Bランク（動作確認済 実用品）',
		'C'      => 'Cランク（ジャンク・パーツ取り）',
	);
	$tk_rows  = array_filter(
		array(
			'メーカー'   => tk_field( 'rs_maker', $tk_id ),
			'型式・型番' => tk_field( 'rs_model', $tk_id ),
			'年式'       => tk_field( 'rs_year', $tk_id ),
			'状態'       => $tk_conds[ (string) tk_field( 'rs_condition', $tk_id ) ] ?? '',
			'買取地域'   => tk_field( 'rs_area', $tk_id ),
			'買取日'     => get_the_date( 'Y年n月j日' ),
		)
	);
	?>
	<main id="main" class="container">
		<div class="article-wrap">
			<article>
				<?php tk_breadcrumb( array( array( tk_genre_label( $tk_genre ) . '買取実績', tk_results_url( $tk_genre ) ), array( get_the_title() ) ) ); ?>
				<h1 class="page-hero__title" style="font-size:clamp(24px,4vw,38px)"><?php the_title(); ?></h1>
				<?php if ( $tk_price ) : ?>
					<p class="result-price-big">買取価格 <b><?php echo esc_html( number_format( $tk_price ) ); ?></b>円</p>
				<?php endif; ?>
				<?php echo tk_media( $tk_id, 'tk-wide', 'PHOTO', 'tool' === $tk_genre ? 'linear-gradient(135deg,#3a3a3e,#111)' : 'linear-gradient(135deg,#6aa36f,#2f6b3a)', 'article-eyecatch' ); // phpcs:ignore ?>
				<div class="prose">
					<div class="table-wrap"><table><tbody>
						<?php foreach ( $tk_rows as $tk_k => $tk_v ) : ?>
							<tr><th><?php echo esc_html( $tk_k ); ?></th><td><?php echo esc_html( $tk_v ); ?></td></tr>
						<?php endforeach; ?>
					</tbody></table></div>
					<?php the_content(); ?>
				</div>
				<aside class="inline-cta">
					<p>同じような<?php echo esc_html( tk_genre_label( $tk_genre ) ?: '品物' ); ?>をお持ちの方へ<small>動かない・古い品も査定します。出張費・査定料・キャンセル料0円。</small></p>
					<a class="btn" href="<?php echo esc_url( tk_form_url( $tk_genre ) ); ?>"><?php echo tk_icon( 'edit' ); // phpcs:ignore ?>無料査定フォームへ</a>
				</aside>
			</article>
		</div>
	</main>
	<?php
endwhile;
get_footer();
