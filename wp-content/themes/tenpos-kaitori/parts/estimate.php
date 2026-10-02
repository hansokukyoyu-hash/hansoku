<?php
/**
 * 査定セクション（CF7 フォーム＋安心の約束）
 *
 * $args: genre（agri / tool）
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

$tk_genre = $args['genre'] ?? 'agri';
$tk_tool  = 'tool' === $tk_genre;
?>
<section class="section cv-section--<?php echo esc_attr( $tk_genre ); ?>" id="form" data-nav="無料査定">
	<div class="container">
		<div class="sec-head reveal">
			<span class="eyebrow">Free Estimate</span>
			<h2 class="sec-title"><?php echo $tk_tool ? '型番を送って、即査定。' : 'まずは無料査定から'; ?></h2>
		</div>
		<div class="cv-wrap">
			<div class="form-card reveal">
				<h3>かんたん査定フォーム</h3>
				<p class="lead"><?php echo $tk_tool ? '入力は1分で完了します。' : '入力は1分で完了します。担当者からお電話でご連絡します。'; ?></p>
				<?php tk_render_cf7( $tk_tool ? 'tk_cf7_tool' : 'tk_cf7_agri' ); ?>
				<p class="micro"><span>査定料・キャンセル料0円</span><span>強引な営業は一切ありません</span></p>
			</div>
			<div class="side-card reveal" style="--d:.1s">
				<span class="side-card__bubble"></span>
				<h3>安心してご依頼いただける<br>3つの約束</h3>
				<ol><li>査定料・出張費・キャンセル料0円</li><li>金額にご納得いただけなければ断ってOK</li><li>強引な営業・しつこい電話は一切なし</li></ol>
				<p class="side-card__tel">お急ぎの方はお電話でも<a href="<?php echo esc_url( tk_tel_href() ); ?>"><?php echo tk_icon( 'phone' ); // phpcs:ignore ?><?php echo esc_html( tk_opt( 'tk_tel' ) ); ?></a><small><?php echo esc_html( tk_opt( 'tk_tel_hours' ) ); ?></small></p>
			</div>
		</div>
	</div>
</section>
