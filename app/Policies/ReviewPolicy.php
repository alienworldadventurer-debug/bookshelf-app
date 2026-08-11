<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * レビューを更新（編集画面の表示および更新処理）できるか判定
     */
    public function update(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }

    /**
     * レビューを削除できるか判定
     */
    public function delete(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }
}