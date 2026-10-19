<?php
/**
 * フッター＋フローティング Dock＋メニューシート
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
<footer class="site-footer">
	<div class="container">
		<div class="site-footer__grid">
			<div>
				<?php get_template_part( 'parts/brand', null, array( 'name' => $tk_brand_name ) ); ?>
				<p class="site-footer__company">運営会社：<?php echo esc_html( tk_opt( 'tk_company' ) ); ?><br>古物商許可番号：<?php echo esc_html( tk_opt( 'tk_license' ) ); ?></p>
			</div>
			<div>
				<h2 class="site-footer__h">SERVICE</h2>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">トップ</a></li>
					<li><a href="<?php echo esc_url( tk_page_url( 'agricultural-equipment' ) ); ?>">農機具買取</a></li>
					<li><a href="<?php echo esc_url( tk_page_url( 'tool' ) ); ?>">工具買取</a></li>
					<li><a href="<?php echo esc_url( tk_results_url() ); ?>">買取実績</a></li>
					<li><a href="<?php echo esc_url( tk_page_url( 'area' ) ); ?>">対応エリア・店舗</a></li>
				</ul>
			</div>
			<div>
				<h2 class="site-footer__h">SUPPORT</h2>
				<?php
				if ( has_nav_menu( 'footer' ) ) {
					wp_nav_menu(
						array(
							'theme_location' => 'footer',
							'container'      => false,
							'depth'          => 1,
						)
					);
				} else {
					?>
					<ul>
						<li><a href="<?php echo esc_url( get_post_type_archive_link( 'column' ) ); ?>">コラム</a></li>
						<li><a href="<?php echo esc_url( get_post_type_archive_link( 'glossary' ) ); ?>">用語集</a></li>
						<li><a href="<?php echo esc_url( get_post_type_archive_link( 'faq' ) ); ?>">よくある質問</a></li>
						<li><a href="<?php echo esc_url( tk_page_url( 'contact' ) ); ?>">お問い合わせ</a></li>
						<?php if ( get_privacy_policy_url() ) : ?>
							<li><a href="<?php echo esc_url( get_privacy_policy_url() ); ?>">プライバシーポリシー</a></li>
						<?php endif; ?>
						<?php if ( tk_page_published( 'terms' ) ) : ?>
							<li><a href="<?php echo esc_url( tk_page_url( 'terms' ) ); ?>">利用規約</a></li>
						<?php endif; ?>
					</ul>
				<?php } ?>
			</div>
		</div>
		<p class="site-footer__copy">© TEMPOS BUSTERS Co., Ltd.</p>
	</div>
</footer>

<?php get_template_part( 'parts/dock' ); ?>
<?php wp_footer(); ?>
</body>
</html>
