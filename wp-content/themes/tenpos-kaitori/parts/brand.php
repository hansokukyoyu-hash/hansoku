<?php
/**
 * ロゴ＋サービス名
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

$tk_logo_id = (int) get_theme_mod( 'custom_logo' );
?>
<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
	<?php
	if ( $tk_logo_id ) {
		echo wp_get_attachment_image(
			$tk_logo_id,
			'medium',
			false,
			array(
				'class'    => 'brand__logo',
				'alt'      => 'テンポス',
				'loading'  => 'eager',
				'decoding' => 'async',
			)
		);
	} else {
		printf(
			'<img class="brand__logo" src="%s" alt="テンポス" width="79" height="40" decoding="async">',
			esc_url( TK_URI . '/assets/img/logo_tepos_dr-tenpos.png' )
		);
	}
	?>
	<span class="brand__name"><?php echo esc_html( $args['name'] ?? '農機具・工具買取' ); ?><small>テンポスバスターズ</small></span>
</a>
