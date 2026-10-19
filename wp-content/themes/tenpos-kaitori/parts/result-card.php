<?php
/**
 * 買取実績カード（ループ内で使用）
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

$tk_id    = get_the_ID();
$tk_genre = tk_post_genre( $tk_id );
$tk_cond  = (string) tk_field( 'rs_condition', $tk_id );
$tk_map   = array(
	'ok'     => array( '稼働', 'ok', 'ok' ),
	'ng'     => array( '不動', 'ng', 'ng' ),
	'broken' => array( '故障', 'ng', 'ng' ),
	'S'      => array( 'Sランク', 'ok', 'S' ),
	'A'      => array( 'Aランク', 'ok', 'A' ),
	'B'      => array( 'Bランク', 'ok', 'B' ),
	'C'      => array( 'Cランク', 'ng', 'C' ),
);
[ $tk_cond_label, $tk_cond_cls, $tk_state ] = $tk_map[ $tk_cond ] ?? array( '', '', '' );
$tk_price = (int) tk_field( 'rs_price', $tk_id );
$tk_bg    = 'tool' === $tk_genre ? 'linear-gradient(135deg,#3a3a3e,#111)' : 'linear-gradient(135deg,#6aa36f,#2f6b3a)';
$tk_delay = isset( $args['i'] ) ? sprintf( ' style="--d:%.2fs"', ( $args['i'] % 4 ) * 0.08 ) : '';
?>
<article class="result-card reveal" data-state="<?php echo esc_attr( $tk_state ); ?>"<?php echo $tk_delay; // phpcs:ignore ?>>
	<a href="<?php the_permalink(); ?>" class="result-card__link" aria-label="<?php the_title_attribute(); ?>">
		<?php echo tk_media( $tk_id, 'tk-card', 'PHOTO', $tk_bg ); // phpcs:ignore ?>
	</a>
	<div class="result-card__body">
		<div class="result-card__meta">
			<?php if ( $tk_cond_label ) : ?>
				<span class="chip chip--<?php echo esc_attr( $tk_cond_cls ); ?>"><?php echo esc_html( $tk_cond_label ); ?></span>
			<?php endif; ?>
			<?php if ( tk_field( 'rs_area', $tk_id ) ) : ?>
				<span class="chip"><?php echo esc_html( tk_field( 'rs_area', $tk_id ) ); ?></span>
			<?php endif; ?>
			<span class="chip"><?php echo esc_html( get_the_date( 'Y.m.d' ) ); ?></span>
		</div>
		<h3 class="result-card__name"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		<?php if ( $tk_price ) : ?>
			<p class="result-card__price">買取価格 <b><?php echo esc_html( number_format( $tk_price ) ); ?></b>円</p>
		<?php endif; ?>
	</div>
</article>
