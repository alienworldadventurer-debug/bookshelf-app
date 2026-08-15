<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;

class ReviewLikeController extends Controller
{
    /**
     * レビューへのいいね登録・解除のトグル処理
     */
    public function store(Review $review): RedirectResponse
    {
        // ログイン中のユーザーのいいねしたレビューリレーションに対して、対象レビューIDをトグルします
        auth()->user()->likedReviews()->toggle($review->id);

        // トグル操作完了後、書籍詳細画面などの直前の画面にリダイレクトします
        return back();
    }
}
