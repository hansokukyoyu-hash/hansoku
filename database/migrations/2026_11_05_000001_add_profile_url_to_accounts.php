<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // アカウントのプロフィールURL(API連携は収集時に自動で入れる。TikTok・手入力は編集画面で入れる)
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('profile_url', 1024)->nullable()->after('external_id');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('profile_url');
        });
    }
};
