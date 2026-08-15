<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ReviewLikeController;
use App\Http\Controllers\RankingController;
use Illuminate\Support\Facades\Route;

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

// ランキング画面
Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');

Route::middleware(['auth'])->group(function () {
    Route::resource('books', BookController::class)->except(['index', 'show']);

    // レビュー投稿用（特定の書籍に対して投稿するため URL に {book} が入ります）
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');

    // レビュー編集・更新・削除用
    Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])->name('reviews.edit');
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

    // お気に入り一覧画面
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');

    // お気に入りトグル処理（書籍のIDをルートパラメーターに持つ）
    Route::post('/books/{book}/favorites', [FavoriteController::class, 'store'])->name('favorites.toggle');

    // レビューいいねトグル処理（レビューのIDをルートパラメーターに持つ）
    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'store'])->name('reviews.like');
});
