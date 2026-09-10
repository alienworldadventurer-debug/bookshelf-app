<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;

class ReviewLikeController extends Controller
{
    /**
     * レビューへのいいね登録状態を切り替える。
     *
     * @param  Review  $review  いいね対象のレビュー
     * @return RedirectResponse 直前の画面へのリダイレクト
     */
    public function store(Review $review): RedirectResponse
    {
        auth()->user()->likedReviews()->toggle($review->id);

        return back();
    }
}
