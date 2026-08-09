<?php

use App\Http\Controllers\BookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// トップページ（/）アクセス時は書籍一覧（/books）へリダイレクト
Route::get('/', function () {
    return redirect()->route('books.index');
});

// 【ゲストでもアクセス可能】書籍一覧（index）と詳細画面（show）
Route::resource('books', BookController::class)->only(['index', 'show']);

// ジャンル管理一覧（仮置き：エラー防止用）
Route::get('/genres', function () {
    return 'ジャンル管理画面（準備中）';
})->name('genres.index');

// ランキング画面（仮置き：エラー防止用）
Route::get('/ranking', function () {
    return 'ランキング画面（準備中）';
})->name('ranking.index');

// お気に入り一覧（仮置き：エラー防止用）
Route::get('/favorites', function () {
    return 'お気に入り一覧画面（準備中）';
})->name('favorites.index');

// 【ログイン必須】登録（create/store）、編集（edit/update）、削除（destroy）
Route::middleware(['auth'])->group(function () {
    Route::resource('books', BookController::class)->except(['index', 'show']);
});
