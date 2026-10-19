<?php
/**
 * 外観 → 「買取サイト初期設定」
 *
 * 1. 必要な固定ページの作成とフロントページ設定
 * 2. ジャンル（農機具・工具・買取手続き）の作成
 * 3. サンプルコンテンツ（用語集・連載・FAQ・買取実績）の投入（任意）
 *
 * いずれも「まだ無いものだけ作る」ので、何度実行しても重複しません。
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

require_once TK_DIR . '/inc/sample-data.php';
require_once TK_DIR . '/inc/privacy.php';

/**
 * 必要な固定ページ（slug => [タイトル, 本文]）
 */
function tk_required_pages(): array {
	return array(
		'top'                    => array( 'トップ', '' ),
		'agricultural-equipment' => array( '農機具買取', '' ),
		'tool'                   => array( '工具買取', '' ),
		'area'                   => array( '対応エリア・店舗案内', '<!-- wp:paragraph --><p>対応エリア・店舗情報をここに記載します。</p><!-- /wp:paragraph -->' ),
		'contact'                => array( 'お問い合わせ・無料査定', '' ),
		'terms'                  => array( '利用規約', '' ),
	);
}

function tk_setup_pages(): array {
	$log = array();
	foreach ( tk_required_pages() as $slug => [ $title, $content ] ) {
		$page = get_page_by_path( $slug );
		if ( $page ) {
			if ( 'publish' !== $page->post_status ) {
				$log[] = "固定ページ「{$page->post_title}」（/{$slug}/）は {$page->post_status} 状態です。内容を確認して公開してください";
			}
			continue;
		}
		$id    = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $content,
			)
		);
		$log[] = "固定ページ「{$title}」（/{$slug}/）を作成";
		if ( 'top' === $slug && $id && ! is_wp_error( $id ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $id );
			$log[] = 'フロントページを「トップ」に設定';
		}
	}
	return $log;
}

add_action(
	'admin_menu',
	function () {
		add_theme_page( '買取サイト初期設定', '買取サイト初期設定', 'edit_theme_options', 'tk-setup', 'tk_setup_screen' );
	}
);

function tk_setup_screen(): void {
	$log = array();
	if ( isset( $_POST['tk_setup'] ) && check_admin_referer( 'tk_setup' ) && current_user_can( 'edit_theme_options' ) ) {
		tk_ensure_genres();
		$log   = tk_setup_pages();
		$log[] = tk_setup_privacy_page();
		if ( ! empty( $_POST['tk_samples'] ) ) {
			$log = array_merge( $log, tk_import_samples() );
		}
		flush_rewrite_rules();
		$log[] = 'パーマリンク設定を更新';
	}
	?>
	<div class="wrap">
		<h1>買取サイト初期設定</h1>
		<p>テーマに必要な固定ページ・ジャンルを作成します。既にあるものは作成しません（何度実行しても安全です）。</p>
		<ul style="list-style:disc;padding-left:20px">
			<?php foreach ( tk_required_pages() as $slug => [ $title ] ) : ?>
				<li><?php echo esc_html( $title ); ?>（/<?php echo esc_html( $slug ); ?>/）<?php
				$tk_p = get_page_by_path( $slug );
				if ( $tk_p ) {
					echo 'publish' === $tk_p->post_status ? ' … <strong>公開中</strong>' : ' … <strong style="color:#b32d2e">' . esc_html( get_post_status_object( $tk_p->post_status )?->label ?? $tk_p->post_status ) . '（未公開）</strong>';
				}
				?></li>
			<?php endforeach; ?>
			<li>プライバシーポリシー（/privacy-policy/）<?php
			$tk_pp = get_page_by_path( 'privacy-policy' );
			echo $tk_pp ? ( 'publish' === $tk_pp->post_status ? ' … <strong>公開中</strong>' : ' … <strong style="color:#b32d2e">未公開</strong>' ) : '';
			?> … 未公開の場合、テーマのひな形（買取サービス向け）で下書きを作成します</li>
		</ul>
		<form method="post">
			<?php wp_nonce_field( 'tk_setup' ); ?>
			<p><label><input type="checkbox" name="tk_samples" value="1"> サンプルコンテンツも投入する（用語集23語・連載「離農・農機具整理ガイド」・FAQ・買取実績）</label></p>
			<p><button class="button button-primary" name="tk_setup" value="1">初期設定を実行</button></p>
		</form>
		<?php if ( $log ) : ?>
			<div class="notice notice-success"><ul>
				<?php foreach ( $log as $line ) : ?>
					<li><?php echo esc_html( $line ); ?></li>
				<?php endforeach; ?>
			</ul></div>
		<?php endif; ?>
		<h2>このあと行うこと</h2>
		<ol>
			<li>設定 → パーマリンク：「投稿名」を選択して保存</li>
			<li>Contact Form 7 でフォームを作成（ひな形はテーマの <code>docs/</code> フォルダ）し、外観 → カスタマイズ → 買取サイト設定 にフォームIDを入力</li>
			<li>同じ画面で電話番号・受付時間・古物商許可番号を入力</li>
		</ol>
	</div>
	<?php
}
