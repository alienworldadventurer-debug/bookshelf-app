<?php

use App\Http\Controllers\Api\V1\BookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// バージョン1 (v1) グループ
Route::prefix('v1')->group(function () {
    // 書籍API（認証なしのCRUD一括登録）
    Route::apiResource('books', BookController::class);
});
