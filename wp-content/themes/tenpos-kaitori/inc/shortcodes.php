<?php
/**
 * ショートコード
 *
 * [term slug="meiban"]          … 用語集の解説をポップアップ表示（表示名は用語名）
 * [term slug="meiban"]銘板[/term] … 表示名を指定
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

add_shortcode(
	'term',
	function ( $atts, $content = '' ) {
		$atts = shortcode_atts( array( 'slug' => '' ), $atts, 'term' );
		$post = $atts['slug'] ? get_page_by_path( sanitize_title( $atts['slug'] ), OBJECT, 'glossary' ) : null;
		$label = '' !== trim( (string) $content ) ? $content : ( $post ? get_the_title( $post ) : '' );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return esc_html( $label );
		}

		$def = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		return sprintf(
			'<button type="button" class="term" data-term-name="%s" data-def="%s" data-href="%s">%s</button>',
			esc_attr( get_the_title( $post ) ),
			esc_attr( trim( $def ) ),
			esc_url( get_post_type_archive_link( 'glossary' ) . '#' . $post->post_name ),
			esc_html( $label )
		);
	}
);
