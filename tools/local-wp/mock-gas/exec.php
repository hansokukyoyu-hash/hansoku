<?php
// ローカル検証専用：Apps Script ウェブアプリの代わり（POST → 302 → 結果 JSON、の挙動を再現）
// 受け取った内容は mock-gas/rows.jsonl に追記。トークンは環境変数ではなく固定値 LOCALTOKEN123
$data = json_decode( file_get_contents( 'php://input' ), true );
$ok   = is_array( $data ) && ( $data['token'] ?? '' ) === 'LOCALTOKEN123';
if ( $ok ) {
	unset( $data['token'] );
	file_put_contents( __DIR__ . '/rows.jsonl', json_encode( $data, JSON_UNESCAPED_UNICODE ) . "\n", FILE_APPEND );
}
header( 'Location: echo.php?r=' . ( $ok ? 'ok' : 'unauthorized' ), true, 302 );
