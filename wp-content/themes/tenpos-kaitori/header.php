<?php
/**
 * ヘッダー
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

$tk_tone       = tk_theme_tone();
$tk_brand_name = array(
	'hub'  => '農機具・工具買取',
	'agri' => '農機具買取',
	'tool' => '工具買取',
)[ $tk_tone ];
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php if ( 'tool' === $tk_tone ) : ?>
<meta name="theme-color" content="#0e0e0f">
<?php endif; ?>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php get_template_part( 'parts/icons' ); ?>
<a class="sr-only" href="#main">本文へスキップ</a>

<header class="site-header">
	<div class="container site-header__inner">
		<?php get_template_part( 'parts/brand', null, array( 'name' => $tk_brand_name ) ); ?>
		<span class="badge-listed">東証上場グループ運営</span>
		<nav class="site-nav" aria-label="グローバル">
			<?php
			if ( has_nav_menu( 'global' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'global',
						'container'      => false,
						'items_wrap'     => '%3$s',
						'depth'          => 1,
						'walker'         => new class() extends Walker_Nav_Menu {
							public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
								$output .= sprintf( '<a href="%s"%s>%s</a>', esc_url( $item->url ), $item->current ? ' aria-current="page"' : '', esc_html( $item->title ) );
							}
							public function end_el( &$output, $item, $depth = 0, $args = null ) {}
						},
					)
				);
			} else {
				$tk_links = array(
					'農機具買取' => tk_page_url( 'agricultural-equipment' ),
					'工具買取'   => tk_page_url( 'tool' ),
					'買取実績'   => tk_results_url(),
					'コラム'     => get_post_type_archive_link( 'column' ),
					'用語集'     => get_post_type_archive_link( 'glossary' ),
				);
				foreach ( $tk_links as $tk_label => $tk_url ) {
					printf( '<a href="%s">%s</a>', esc_url( $tk_url ), esc_html( $tk_label ) );
				}
			}
			?>
		</nav>
		<a class="header-tel" href="<?php echo esc_url( tk_tel_href() ); ?>" aria-label="<?php echo esc_attr( '電話で相談 ' . tk_opt( 'tk_tel' ) ); ?>">
			<?php echo tk_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="header-tel__label">電話</span><span class="header-tel__num"><?php echo esc_html( tk_opt( 'tk_tel' ) ); ?></span>
		</a>
	</div>
</header>
