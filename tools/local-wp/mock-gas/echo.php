<?php
header( 'Content-Type: application/json' );
echo 'ok' === ( $_GET['r'] ?? '' ) ? '{"ok":true}' : '{"ok":false,"error":"unauthorized"}';
