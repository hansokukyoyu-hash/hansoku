<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 手入力の投稿本数(任意。空欄は「未入力」として 0 本と区別する)
    public function up(): void
    {
        Schema::table('manual_entries', function (Blueprint $table) {
            $table->unsignedInteger('posts')->nullable()->after('views');
        });
    }

    public function down(): void
    {
        Schema::table('manual_entries', function (Blueprint $table) {
            $table->dropColumn('posts');
        });
    }
};
