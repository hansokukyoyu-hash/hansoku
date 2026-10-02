<?php
/**
 * 用語集（/glossary/）
 *
 * 用語は「よみ」の五十音順。行ごとにグループ化し、検索・カテゴリで絞り込み。
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

global $wp_query;
$tk_groups = array();
foreach ( $wp_query->posts as $tk_p ) {
	$tk_groups[ tk_kana_row( (string) tk_field( 'gl_yomi', $tk_p->ID ) ) ][] = $tk_p;
}
$tk_order  = array( 'あ', 'か', 'さ', 'た', 'な', 'は', 'ま', 'や', 'ら', 'わ', '他' );
$tk_groups = array_filter( array_replace( array_fill_keys( $tk_order, array() ), $tk_groups ) );

tk_dock_config(
	array(
		'sheet_title'   => 'INDEX',
		'pct_label'     => '現在',
		'switch_href'   => get_post_type_archive_link( 'column' ),
		'switch_small'  => '用語の実例を読む',
		'switch_strong' => 'お役立ちコラムへ',
		'switch2_href'  => '',
	)
);

get_header();

$tk_tabs = array();
$tk_i    = 0;
foreach ( array_keys( $tk_groups ) as $tk_row ) {
	$tk_tabs[ 'row-' . $tk_i++ ] = '他' === $tk_row ? 'その他' : $tk_row . '行';
}
get_template_part(
	'parts/anchor-tabs',
	null,
	array(
		'items'    => $tk_tabs,
		'numbered' => false,
		'label'    => '五十音',
	)
);
?>
<main id="main">
	<section class="page-hero">
		<div class="container">
			<?php tk_breadcrumb( array( array( '用語集' ) ) ); ?>
			<span class="eyebrow">Glossary</span>
			<h1 class="page-hero__title">用語集</h1>
			<p class="page-hero__lead">農機具・工具の買取でよく出てくることばを、ひとことで解説します。コラム本文の<span class="term" style="cursor:default">点線の用語</span>をタップしても、ここの解説が表示されます。</p>
			<div class="g-tools">
				<label class="g-search"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><span class="sr-only">用語を検索</span><input type="search" placeholder="用語を検索（例：名義、バッテリー）" data-g-search></label>
				<div class="cat-chips" role="group" aria-label="カテゴリで絞り込み" data-g-cats style="margin:0">
					<button type="button" data-value="all" aria-pressed="true">すべて</button>
					<button type="button" data-value="agri" aria-pressed="false">農機具</button>
					<button type="button" data-value="tool" aria-pressed="false">工具</button>
					<button type="button" data-value="deal" aria-pressed="false">買取・手続き</button>
				</div>
				<p class="g-count" aria-live="polite"><b data-g-count><?php echo esc_html( count( $wp_query->posts ) ); ?></b> 語</p>
			</div>
		</div>
	</section>

	<div class="container" style="padding-bottom:96px">
		<?php
		$tk_i = 0;
		foreach ( $tk_groups as $tk_row => $tk_items ) :
			?>
			<section class="g-group" id="row-<?php echo esc_attr( $tk_i++ ); ?>" data-nav="<?php echo esc_attr( '他' === $tk_row ? 'その他' : $tk_row . '行' ); ?>">
				<div class="g-group__head"><h2 class="g-group__kana"><?php echo esc_html( $tk_row ); ?></h2><span class="g-group__n"><span data-group-count><?php echo esc_html( count( $tk_items ) ); ?></span>語</span></div>
				<dl class="g-list">
					<?php
					foreach ( $tk_items as $tk_p ) :
						$tk_genre   = tk_post_genre( $tk_p->ID );
						$tk_yomi    = (string) tk_field( 'gl_yomi', $tk_p->ID );
						$tk_related = array_filter( (array) tk_field( 'gl_related', $tk_p->ID ) );
						$tk_link    = tk_field( 'gl_link', $tk_p->ID );
						?>
						<div class="g-term" id="<?php echo esc_attr( $tk_p->post_name ); ?>" data-cat="<?php echo esc_attr( $tk_genre ); ?>" data-search="<?php echo esc_attr( get_the_title( $tk_p ) . ' ' . $tk_yomi ); ?>">
							<div class="g-term__head">
								<dt><?php echo esc_html( get_the_title( $tk_p ) ); ?></dt>
								<span class="g-term__yomi"><?php echo esc_html( $tk_yomi ); ?></span>
								<?php if ( $tk_genre ) : ?>
									<span class="g-term__cat g-term__cat--<?php echo esc_attr( $tk_genre ); ?>"><?php echo esc_html( tk_genre_label( $tk_genre ) ); ?></span>
								<?php endif; ?>
							</div>
							<dd><?php echo wp_kses_post( wpautop( do_shortcode( $tk_p->post_content ) ) ); ?></dd>
							<?php if ( $tk_related ) : ?>
								<div class="g-term__rel"><span style="color:var(--ink-soft)">関連：</span>
									<?php foreach ( $tk_related as $tk_rid ) : ?>
										<a href="#<?php echo esc_attr( get_post_field( 'post_name', (int) $tk_rid ) ); ?>"><?php echo esc_html( get_the_title( (int) $tk_rid ) ); ?></a>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
							<?php if ( is_array( $tk_link ) && ! empty( $tk_link['url'] ) ) : ?>
								<a class="g-term__more" href="<?php echo esc_url( $tk_link['url'] ); ?>"><?php echo esc_html( $tk_link['title'] ?: '詳しく見る' ); ?><?php echo tk_icon( 'arrow' ); // phpcs:ignore ?></a>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</dl>
			</section>
		<?php endforeach; ?>
		<p class="g-empty" data-g-empty>該当する用語が見つかりませんでした。<br>お困りのことは <a href="<?php echo esc_url( tk_page_url( 'contact' ) ); ?>" style="text-decoration:underline">お問い合わせ</a> からご相談ください。</p>
	</div>
</main>
<?php
get_footer();
