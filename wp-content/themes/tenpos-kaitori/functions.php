<?php
/**
 * TENPOS 買取 テーマ
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

define( 'TK_VERSION', '1.4.1' );
define( 'TK_DIR', get_template_directory() );
define( 'TK_URI', get_template_directory_uri() );

foreach ( array(
	'setup',
	'helpers',
	'post-types',
	'fields',
	'customizer',
	'enqueue',
	'forms',
	'sheets',
	'shortcodes',
	'schema',
	'admin-setup',
) as $tk_file ) {
	require_once TK_DIR . '/inc/' . $tk_file . '.php';
}
