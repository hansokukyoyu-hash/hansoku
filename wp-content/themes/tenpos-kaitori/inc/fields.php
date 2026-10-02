<?php
/**
 * ACF フィールド定義（コードで管理。ACF 管理画面での編集は不要）
 *
 * ACF 未導入でも、同名の post meta / term meta を読むためテーマは動作します。
 *
 * @package tenpos-kaitori
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'acf/init',
	function () {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		$prefs = array( '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県', '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県', '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県', '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県', '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県' );

		// 買取実績.
		acf_add_local_field_group(
			array(
				'key'      => 'group_tk_result',
				'title'    => '買取実績の詳細',
				'fields'   => array(
					array( 'key' => 'field_tk_rs_maker', 'label' => 'メーカー', 'name' => 'rs_maker', 'type' => 'text', 'wrapper' => array( 'width' => 33 ) ),
					array( 'key' => 'field_tk_rs_model', 'label' => '型式・型番', 'name' => 'rs_model', 'type' => 'text', 'wrapper' => array( 'width' => 33 ) ),
					array( 'key' => 'field_tk_rs_year', 'label' => '年式', 'name' => 'rs_year', 'type' => 'text', 'instructions' => '例）2009年式', 'wrapper' => array( 'width' => 33 ) ),
					array(
						'key'     => 'field_tk_rs_condition',
						'label'   => '状態',
						'name'    => 'rs_condition',
						'type'    => 'select',
						'choices' => array(
							'ok'     => '稼働',
							'ng'     => '不動',
							'broken' => '故障',
							'S'      => 'Sランク（新品・未使用）',
							'A'      => 'Aランク（美品）',
							'B'      => 'Bランク（実用品）',
							'C'      => 'Cランク（ジャンク）',
						),
						'wrapper' => array( 'width' => 33 ),
					),
					array( 'key' => 'field_tk_rs_area', 'label' => '買取地域', 'name' => 'rs_area', 'type' => 'select', 'choices' => array_combine( $prefs, $prefs ), 'allow_null' => 1, 'wrapper' => array( 'width' => 33 ) ),
					array( 'key' => 'field_tk_rs_price', 'label' => '買取価格（円）', 'name' => 'rs_price', 'type' => 'number', 'min' => 0, 'wrapper' => array( 'width' => 33 ) ),
				),
				'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'result' ) ) ),
				'position' => 'acf_after_title',
			)
		);

		// コラム.
		acf_add_local_field_group(
			array(
				'key'      => 'group_tk_column',
				'title'    => 'コラムの設定',
				'fields'   => array(
					array( 'key' => 'field_tk_col_episode', 'label' => '連載の回数', 'name' => 'col_episode', 'type' => 'number', 'instructions' => '連載記事のみ。第何回かを入力（右側の「連載」も選択してください）', 'min' => 1, 'wrapper' => array( 'width' => 50 ) ),
					array( 'key' => 'field_tk_col_read', 'label' => '読了目安（分）', 'name' => 'col_read_min', 'type' => 'number', 'min' => 1, 'wrapper' => array( 'width' => 50 ) ),
					array( 'key' => 'field_tk_col_conclusion', 'label' => 'この記事の結論（1文）', 'name' => 'col_conclusion', 'type' => 'text', 'instructions' => '記事冒頭の「この記事の結論」枠に表示。AI検索・強調スニペット対策として結論を先に書きます。' ),
					array( 'key' => 'field_tk_col_points', 'label' => '結論のポイント', 'name' => 'col_points', 'type' => 'textarea', 'rows' => 4, 'new_lines' => '', 'instructions' => '1行に1つ（3つ程度）' ),
				),
				'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'column' ) ) ),
				'position' => 'acf_after_title',
			)
		);

		// 連載（タクソノミー）.
		acf_add_local_field_group(
			array(
				'key'      => 'group_tk_series',
				'title'    => '連載の設定',
				'fields'   => array(
					array( 'key' => 'field_tk_series_total', 'label' => '全何回', 'name' => 'series_total', 'type' => 'number', 'min' => 1, 'instructions' => '未公開の回も路線図に「準備中」として表示されます。予約投稿した回は「公開予定日」で表示されます。' ),
					array( 'key' => 'field_tk_series_genre', 'label' => 'ジャンル', 'name' => 'series_genre', 'type' => 'select', 'choices' => array( 'agri' => '農機具', 'tool' => '工具', 'deal' => '買取・手続き' ) ),
					array( 'key' => 'field_tk_series_lead', 'label' => 'リード文', 'name' => 'series_lead', 'type' => 'textarea', 'rows' => 3, 'new_lines' => '' ),
					array( 'key' => 'field_tk_series_schedule', 'label' => '更新ペース', 'name' => 'series_schedule', 'type' => 'text', 'instructions' => '例）隔週水曜更新' ),
				),
				'location' => array( array( array( 'param' => 'taxonomy', 'operator' => '==', 'value' => 'series' ) ) ),
			)
		);

		// 用語集.
		acf_add_local_field_group(
			array(
				'key'      => 'group_tk_glossary',
				'title'    => '用語の設定',
				'fields'   => array(
					array( 'key' => 'field_tk_gl_yomi', 'label' => 'よみ（ひらがな）', 'name' => 'gl_yomi', 'type' => 'text', 'required' => 1, 'instructions' => '五十音の並び順と「あ行・か行…」の振り分けに使います。' ),
					array( 'key' => 'field_tk_gl_related', 'label' => '関連用語', 'name' => 'gl_related', 'type' => 'relationship', 'post_type' => array( 'glossary' ), 'return_format' => 'id', 'max' => 4 ),
					array( 'key' => 'field_tk_gl_link', 'label' => '詳しく読むリンク', 'name' => 'gl_link', 'type' => 'link', 'return_format' => 'array', 'instructions' => 'LPのセクションやコラム記事など' ),
				),
				'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'glossary' ) ) ),
				'position' => 'acf_after_title',
			)
		);
	}
);
