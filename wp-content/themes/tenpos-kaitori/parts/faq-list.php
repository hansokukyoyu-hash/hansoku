<?php
/**
 * FAQ アコーディオン
 *
 * $args: genre, surface（bool：details の背景を白に）
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

$tk_faqs = tk_get_faqs( $args['genre'] ?? '' );
if ( ! $tk_faqs ) {
	echo '<p class="empty-note">よくある質問は準備中です。</p>';
	return;
}
$tk_style = ! empty( $args['surface'] ) ? ' style="background:var(--bg)"' : '';
?>
<div class="faq">
	<?php foreach ( $tk_faqs as $tk_faq ) : ?>
		<details<?php echo $tk_style; // phpcs:ignore ?>>
			<summary><span class="q">Q</span><?php echo esc_html( get_the_title( $tk_faq ) ); ?><span class="pm" aria-hidden="true"></span></summary>
			<div class="a"><?php echo wp_kses_post( wpautop( do_shortcode( $tk_faq->post_content ) ) ); ?></div>
		</details>
	<?php endforeach; ?>
</div>
