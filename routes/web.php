<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReadingPlanController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::resource('books', BookController::class)->except(['index', 'show']);

    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');

    Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])->name('reviews.edit');

    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');

    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');

    Route::post('/books/{book}/favorites', [FavoriteController::class, 'store'])->name('favorites.toggle');

    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'store'])->name('reviews.like');

    Route::resource('genres', GenreController::class);

    Route::get('/books/isbn/{isbn}', [BookController::class, 'searchByIsbn'])
        ->name('books.isbn.search');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    Route::resource('reading-plans', ReadingPlanController::class)->except(['show']);

    Route::post('reading-plans/{reading_plan}/complete', [ReadingPlanController::class, 'complete'])
        ->name('reading-plans.complete');

    Route::get('notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');

    Route::post('notifications/{id}/read', [NotificationController::class, 'read'])
        ->name('notifications.read');
});

/**
 * トップページへのアクセスを書籍一覧へリダイレクトする。
 *
 * @return RedirectResponse 書籍一覧へのリダイレクトレスポンス
 */
Route::get('/', function (): RedirectResponse {
    return redirect()->route('books.index');
});

Route::resource('books', BookController::class)->only(['index', 'show']);

Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');
