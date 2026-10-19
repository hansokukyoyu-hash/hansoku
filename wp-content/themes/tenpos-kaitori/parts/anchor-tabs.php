<?php
/**
 * ページ内目次タブ（ヘッダー直下に追従）
 *
 * $args: items => [ id => ラベル ], numbered（bool）
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

$tk_items = $args['items'] ?? array();
if ( ! $tk_items ) {
	return;
}
$tk_n = 0;
?>
<nav class="anchor-tabs" aria-label="<?php echo esc_attr( $args['label'] ?? 'ページ内目次' ); ?>">
	<div class="anchor-tabs__track">
		<span class="anchor-tabs__pill" aria-hidden="true"></span>
		<?php foreach ( $tk_items as $tk_id => $tk_label ) : ?>
			<a href="#<?php echo esc_attr( $tk_id ); ?>"><?php if ( $args['numbered'] ?? true ) : ?><span class="num"><?php echo esc_html( sprintf( '%02d', ++$tk_n ) ); ?></span><?php endif; ?><?php echo esc_html( $tk_label ); ?></a>
		<?php endforeach; ?>
	</div>
</nav>
