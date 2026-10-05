<?php

use Illuminate\Support\Facades\Schedule;

// サーバーの cron で「php artisan schedule:run」を毎分実行するか、
// 「php artisan sns:collect」を5分おきに直接実行する(README参照)。
Schedule::command('sns:collect')->everyFiveMinutes();
