<?php
/**
 * ローカル検証専用：スプレッドシート連携の送信先として localhost の擬似 Apps Script を許可（本番には入れない）
 */
add_filter( 'tk_sheets_allow_url', fn( $ok, $url ) => $ok || str_starts_with( $url, 'http://localhost:' ), 10, 2 );
