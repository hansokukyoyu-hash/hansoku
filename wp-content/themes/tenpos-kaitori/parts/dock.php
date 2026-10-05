<?php
/**
 * フローティング Dock（電話／メニュー／WEB査定）とメニューシート
 *
 * 各テンプレートで tk_dock_config( [...] ) を呼ぶと上書きできます。
 *   form_href / form_small / form_label / tel_small / sheet_title / pct_label / switch_href / switch_small / switch_strong
 *
 * トップページの右ボタンは「買取の総合問い合わせ」（/contact/）。それ以外は WEB査定（LP の #form、ハブ系ページはトップの #select）。
 *
 * シートの目次は本文中の [data-nav] 要素から JS が自動生成します。
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

$tk_tone = tk_theme_tone();

$tk_defaults = array(
	'hub'  => array(
		'form_href'     => is_front_page() ? tk_page_url( 'contact' ) : home_url( '/#select' ),
		'form_small'    => is_front_page() ? '買取の総合窓口' : '入力1分・無料',
		'form_label'    => is_front_page() ? 'お問い合わせ' : 'WEB査定',
		'tel_small'     => '無料・年中無休',
		'sheet_title'   => 'MENU',
		'pct_label'     => 'このページ',
		'switch_href'   => tk_page_url( 'agricultural-equipment' ),
		'switch_small'  => '農家・離農・ご遺族の方',
		'switch_strong' => '農機具を売る',
		'switch2_href'  => tk_page_url( 'tool' ),
		'switch2_small' => '職人・工務店の方',
		'switch2_strong' => '工具を売る',
	),
	'agri' => array(
		'form_href'     => is_page( 'agricultural-equipment' ) ? '#form' : tk_form_url( 'agri' ),
		'form_small'    => '入力1分・無料',
		'tel_small'     => '無料・年中無休',
		'sheet_title'   => 'INDEX',
		'pct_label'     => '読了',
		'switch_href'   => tk_page_url( 'tool' ),
		'switch_small'  => '電動工具・作業工具もまとめて',
		'switch_strong' => '工具買取はこちら',
	),
	'tool' => array(
		'form_href'     => is_page( 'tool' ) ? '#form' : tk_form_url( 'tool' ),
		'form_small'    => '型番を入れるだけ',
		'tel_small'     => '職人専用ダイヤル',
		'sheet_title'   => 'INDEX',
		'pct_label'     => '読了',
		'switch_href'   => tk_page_url( 'agricultural-equipment' ),
		'switch_small'  => 'トラクター・草刈機なども',
		'switch_strong' => '農機具買取はこちら',
	),
);
$c = array_merge( $tk_defaults[ $tk_tone ], tk_dock_config() );
?>
<div class="dock" data-dock>
	<div class="dock__chip" aria-hidden="true"><span class="dock__chip-num">01</span><span class="dock__chip-label"></span></div>
	<div class="dock__bar">
		<a class="dock__btn dock__btn--tel" href="<?php echo esc_url( tk_tel_href() ); ?>"><?php echo tk_icon( 'phone' ); // phpcs:ignore ?><span><small><?php echo esc_html( $c['tel_small'] ); ?></small>電話で相談</span></a>
		<button class="dock__orb" type="button" aria-expanded="false" aria-controls="sheet" aria-label="メニューを開く">
			<svg class="dock__ring" viewBox="0 0 40 40" aria-hidden="true"><circle class="dock__ring-track" cx="20" cy="20" r="18" pathLength="100"/><circle class="dock__ring-bar" cx="20" cy="20" r="18" pathLength="100"/></svg>
			<span class="dock__orb-icon" aria-hidden="true"><i></i><i></i></span>
		</button>
		<a class="dock__btn dock__btn--form" href="<?php echo esc_url( $c['form_href'] ); ?>"><?php echo tk_icon( 'edit' ); // phpcs:ignore ?><span><small><?php echo esc_html( $c['form_small'] ); ?></small><?php echo esc_html( $c['form_label'] ?? 'WEB査定' ); ?></span></a>
	</div>
</div>

<div class="sheet" id="sheet" aria-hidden="true" role="dialog" aria-label="メニュー">
	<div class="sheet__inner">
		<div class="sheet__head" data-stagger>
			<p class="sheet__title"><?php echo esc_html( $c['sheet_title'] ); ?></p>
			<p class="sheet__progress"><?php echo esc_html( $c['pct_label'] ); ?><b data-sheet-pct>0%</b></p>
		</div>
		<ol class="sheet__list"></ol>
		<a class="sheet__switch" href="<?php echo esc_url( $c['switch_href'] ); ?>" data-stagger><span><small><?php echo esc_html( $c['switch_small'] ); ?></small><strong><?php echo esc_html( $c['switch_strong'] ); ?></strong></span><span class="go"><?php echo tk_icon( 'arrow' ); // phpcs:ignore ?></span></a>
		<?php if ( ! empty( $c['switch2_href'] ) ) : ?>
			<a class="sheet__switch" href="<?php echo esc_url( $c['switch2_href'] ); ?>" data-stagger style="margin-top:10px"><span><small><?php echo esc_html( $c['switch2_small'] ); ?></small><strong><?php echo esc_html( $c['switch2_strong'] ); ?></strong></span><span class="go"><?php echo tk_icon( 'arrow' ); // phpcs:ignore ?></span></a>
		<?php endif; ?>
		<div class="sheet__links" data-stagger>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">トップ</a>
			<a href="<?php echo esc_url( tk_results_url( 'hub' === $tk_tone ? '' : $tk_tone ) ); ?>">買取実績</a>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'column' ) ); ?>">コラム</a>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'glossary' ) ); ?>">用語集</a>
			<a href="<?php echo esc_url( get_post_type_archive_link( 'faq' ) ); ?>">よくある質問</a>
			<a href="<?php echo esc_url( tk_page_url( 'contact' ) ); ?>">お問い合わせ</a>
		</div>
		<p class="sheet__note" data-stagger>査定料・出張費・キャンセル料 0円｜強引な営業は一切ありません</p>
	</div>
</div>
