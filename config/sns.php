<?php

return [
    // 初回ログイン時に管理者として自動登録するGoogleアカウント(カンマ区切り)
    'admin_emails' => array_filter(array_map('trim', explode(',', (string) env('ADMIN_EMAILS', '')))),

    // ストーリーズを収集する間隔(時間)
    'stories_interval_hours' => (int) env('SNS_STORIES_INTERVAL_HOURS', 2),

    // 日次収集を始める時刻(この時刻を過ぎたら、その日まだ収集していないアカウントを順に処理する)
    'daily_collect_hour' => (int) env('SNS_DAILY_COLLECT_HOUR', 3),

    // cron 1回あたりに処理するアカウント数(実行時間の上限対策で小さく保つ)
    'accounts_per_run' => (int) env('SNS_ACCOUNTS_PER_RUN', 3),

    // 1回の日次収集で閲覧数を取り直す投稿の範囲(投稿日からの日数)
    'refresh_posts_days' => (int) env('SNS_REFRESH_POSTS_DAYS', 90),

    // Facebookページ投稿の閲覧数に使う指標名(Meta側の指標名変更に備えて設定で差し替え可能)
    'facebook_post_views_metric' => env('SNS_FACEBOOK_POST_VIEWS_METRIC', 'post_media_view'),
];
