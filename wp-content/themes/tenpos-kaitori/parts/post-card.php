<?php
/**
 * コラムカード（ループ内）
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

$tk_id     = get_the_ID();
$tk_genre  = tk_post_genre( $tk_id );
$tk_series = tk_post_series( $tk_id );
$tk_ep     = (int) tk_field( 'col_episode', $tk_id );
$tk_bg     = array(
	'agri' => 'linear-gradient(135deg,#6aa36f,#2f6b3a)',
	'tool' => 'linear-gradient(135deg,#3a3a3e,#111)',
	'deal' => 'linear-gradient(135deg,#c9c2ad,#8f8771)',
)[ $tk_genre ] ?? 'linear-gradient(135deg,#d9d4c4,#b9b19a)';
?>
<a class="post-card reveal" href="<?php the_permalink(); ?>" data-state="<?php echo esc_attr( $tk_genre ); ?>">
	<?php echo tk_media( $tk_id, 'tk-card', 'EYECATCH', $tk_bg ); // phpcs:ignore ?>
	<div class="post-card__body">
		<div class="post-card__meta">
			<?php if ( $tk_series ) : ?>
				<span class="ser-label<?php echo 'tool' === $tk_genre ? ' ser-label--tool' : ''; ?>">連載</span>
			<?php endif; ?>
			<?php if ( $tk_genre ) : ?>
				<span class="chip"><?php echo esc_html( tk_genre_label( $tk_genre ) ); ?></span>
			<?php endif; ?>
			<span><?php echo esc_html( get_the_date( 'Y.m.d' ) ); ?></span>
		</div>
		<?php if ( $tk_series ) : ?>
			<span class="post-card__series"><?php echo esc_html( $tk_series->name . ( $tk_ep ? " 第{$tk_ep}回" : '' ) ); ?></span>
		<?php endif; ?>
		<h3 class="post-card__title"><?php the_title(); ?></h3>
	</div>
</a>
