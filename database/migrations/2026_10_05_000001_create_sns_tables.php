<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('brand_user', function (Blueprint $table) {
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['brand_id', 'user_id']);
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('platform');            // instagram / facebook / youtube / threads / tiktok / x
            $table->string('name');
            $table->string('input_method');        // api / manual
            $table->string('external_id')->nullable();
            $table->text('credentials')->nullable(); // 暗号化して保存
            $table->string('status')->default('not_connected'); // not_connected / active / error
            $table->text('last_error')->nullable();
            $table->timestamp('daily_synced_at')->nullable();
            $table->timestamp('stories_synced_at')->nullable();
            $table->timestamps();
            $table->index(['brand_id', 'platform']);
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('format');              // feed / reel / story / post / short / video
            $table->timestamp('published_at')->nullable();
            $table->string('permalink', 1024)->nullable();
            $table->string('thumbnail_url', 2048)->nullable();
            $table->string('caption', 255)->nullable();
            $table->unsignedBigInteger('views')->default(0); // 最新の累計
            $table->timestamp('views_fetched_at')->nullable();
            $table->timestamps();
            $table->unique(['account_id', 'external_id']);
            $table->index(['account_id', 'published_at']);
        });

        // 投稿ごとの累計閲覧数を日ごとに1件保存する(その日の最終値で上書き)
        Schema::create('post_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('views');
            $table->timestamp('fetched_at');
            $table->unique(['post_id', 'date']);
        });

        // モード「ア」(期間中に発生した閲覧数)で使う日次値
        Schema::create('daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('format');
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();
            $table->unique(['account_id', 'date', 'format']);
        });

        // 手入力分(期間ごとの合計)
        Schema::create('manual_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('format');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('views');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['account_id', 'period_start', 'period_end']);
        });

        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('job');                 // daily / stories
            $table->string('status');              // success / failed
            $table->text('message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->index(['account_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
        Schema::dropIfExists('manual_entries');
        Schema::dropIfExists('daily_metrics');
        Schema::dropIfExists('post_snapshots');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('brand_user');
        Schema::dropIfExists('brands');
    }
};
