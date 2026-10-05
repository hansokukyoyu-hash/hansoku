<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ManualEntryController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::get('/auth/google', [AuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [AuthController::class, 'callback'])->name('auth.google.callback');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/export.csv', [DashboardController::class, 'csv'])->name('dashboard.csv');

    Route::get('/manual', [ManualEntryController::class, 'index'])->name('manual.index');
    Route::get('/manual/{account}', [ManualEntryController::class, 'show'])->name('manual.show');
    Route::post('/manual/{account}', [ManualEntryController::class, 'store'])->name('manual.store');
    Route::delete('/manual/entries/{entry}', [ManualEntryController::class, 'destroy'])->name('manual.destroy');

    Route::middleware('admin')->group(function () {
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::resource('brands', Admin\BrandController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::resource('accounts', Admin\AccountController::class)->except(['show']);
            Route::post('accounts/{account}/collect', [Admin\AccountController::class, 'collect'])->name('accounts.collect');
            Route::get('accounts/{account}/connect', [Admin\ConnectController::class, 'redirect'])->name('accounts.connect');
            Route::resource('members', Admin\MemberController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::get('logs', [Admin\SyncLogController::class, 'index'])->name('logs');
        });

        // 各SNSの開発者画面に登録する戻り先URL
        Route::get('/connect/meta/callback', [Admin\ConnectController::class, 'metaCallback'])->name('connect.meta.callback');
        Route::get('/connect/meta/choose', [Admin\ConnectController::class, 'metaChoose'])->name('connect.meta.choose');
        Route::post('/connect/meta/choose', [Admin\ConnectController::class, 'metaSave'])->name('connect.meta.save');
        Route::get('/connect/threads/callback', [Admin\ConnectController::class, 'threadsCallback'])->name('connect.threads.callback');
        Route::get('/connect/google/callback', [Admin\ConnectController::class, 'googleCallback'])->name('connect.google.callback');
    });
});
