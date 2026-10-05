<?php
/**
 * 農機具 LP：「アフリカで再活用」セクション（当面の主訴求）
 *
 * 文言は外観 → カスタマイズ → 農機具LP：アフリカ訴求 で編集。
 * 4ステップの流れはテーマ固定（事実：買取 → 整備 → アフリカへ輸出 → 現地で再活用）。
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

$tk_title = trim( tk_opt( 'tk_africa_title' ) );
$tk_lead  = trim( tk_opt( 'tk_africa_lead' ) );
$tk_img   = (int) tk_opt( 'tk_africa_image' );
$tk_steps = array(
	array( 'gear', '納屋で眠る農機具', '使わなくなった農機具、動かない農機具もご相談ください。' ),
	array( 'doc', 'テンポスが買取・整備', '査定・引き取りは無料。整備して、再び使える状態にします。' ),
	array( 'globe', '海を渡ってアフリカへ', '輸出ルートを通じて、アフリカの農業の現場へ届けます。' ),
	array( 'truck', '現地の農業で再び活躍', '次の担い手のもとで、もう一度田畑を耕します。' ),
);
?>
<section class="section africa" id="africa" data-nav="アフリカで再活用">
	<div class="container africa__grid">
		<div>
			<div class="sec-head reveal">
				<span class="eyebrow">Second Life in Africa</span>
				<h2 class="sec-title"><?php echo nl2br( esc_html( $tk_title ) ); // phpcs:ignore ?></h2>
				<?php if ( $tk_lead ) : ?>
					<p class="answer"><?php echo esc_html( $tk_lead ); ?></p>
				<?php endif; ?>
			</div>
			<ol class="africa-journey">
				<?php foreach ( $tk_steps as $tk_i => [ $tk_icon, $tk_h, $tk_p ] ) : ?>
					<li class="reveal" style="--d:<?php echo esc_attr( $tk_i * 0.08 ); ?>s">
						<span class="africa-journey__ico"><?php echo tk_icon( $tk_icon ); // phpcs:ignore ?></span>
						<span class="africa-journey__no">STEP <?php echo esc_html( $tk_i + 1 ); ?></span>
						<strong><?php echo esc_html( $tk_h ); ?></strong>
						<span><?php echo esc_html( $tk_p ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
			<div class="africa__cta reveal">
				<a class="btn btn--cta" href="#form"><?php echo tk_icon( 'edit' ); // phpcs:ignore ?>無料査定で次の担い手へ</a>
				<p class="micro"><span>出張費・査定料0円</span><span>動かない農機具もOK</span></p>
			</div>
		</div>
		<div class="africa__visual reveal-clip">
			<?php if ( $tk_img && wp_attachment_is_image( $tk_img ) ) : ?>
				<div class="ph ph--img"><?php echo wp_get_attachment_image( $tk_img, 'tk-wide', false, array( 'loading' => 'lazy' ) ); ?></div>
			<?php else : ?>
				<div class="africa-route" aria-hidden="true">
					<svg viewBox="0 0 320 240" fill="none">
						<path class="africa-route__path" d="M262 70 C 210 10, 110 20, 82 150" stroke-width="3" stroke-linecap="round" stroke-dasharray="2 10"/>
						<circle cx="262" cy="70" r="9" class="africa-route__dot"/>
						<circle cx="82" cy="150" r="9" class="africa-route__dot africa-route__dot--to"/>
						<circle cx="82" cy="150" r="20" class="africa-route__ring"/>
					</svg>
					<span class="africa-route__label africa-route__label--from">JAPAN<small>買取・整備</small></span>
					<span class="africa-route__label africa-route__label--to">AFRICA<small>現地の農業で再活躍</small></span>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
