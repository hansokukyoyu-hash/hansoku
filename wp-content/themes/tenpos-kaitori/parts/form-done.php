<?php
/**
 * フォーム送信完了の表示（CF7 の送信成功＝wpcf7mailsent で JS が表示に切り替える）
 *
 * $args: type（estimate＝査定 / contact＝総合問い合わせ）
 * 連絡までの時間など、根拠のない約束は書かない。
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

$tk_contact = 'contact' === ( $args['type'] ?? 'estimate' );
?>
<div class="form-done" tabindex="-1" aria-live="polite">
	<span class="form-done__icon" aria-hidden="true"><?php echo tk_icon( 'check' ); // phpcs:ignore ?></span>
	<p class="form-done__title">送信が完了しました</p>
	<p class="form-done__text">
		<?php if ( $tk_contact ) : ?>
			<span>お問い合わせいただき、</span><span>ありがとうございます。</span><br><span>内容を確認のうえ、</span><span>担当者よりご連絡いたします。</span>
		<?php else : ?>
			<span>査定のご依頼、</span><span>ありがとうございます。</span><br><span>内容を確認のうえ、</span><span>担当者よりお電話でご連絡いたします。</span>
		<?php endif; ?>
	</p>
	<p class="form-done__tel">お急ぎの方はお電話でもご相談いただけます<a href="<?php echo esc_url( tk_tel_href() ); ?>"><?php echo tk_icon( 'phone' ); // phpcs:ignore ?><?php echo esc_html( tk_opt( 'tk_tel' ) ); ?></a><small><?php echo esc_html( tk_opt( 'tk_tel_hours' ) ); ?></small></p>
	<a class="form-done__back" href="<?php echo esc_url( home_url( '/' ) ); ?>">トップページへ戻る</a>
</div>
