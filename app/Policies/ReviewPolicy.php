<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * レビューを更新（編集画面の表示および更新処理）できるか判定する。
     *
     * @param  User  $user  認証済みユーザー
     * @param  Review  $review  対象レビュー
     * @return bool 更新を許可する場合はtrue
     */
    public function update(User $user, Review $review): bool
    {
        return $this->ownsReview($user, $review);
    }

    /**
     * レビューを削除できるか判定する。
     *
     * @param  User  $user  認証済みユーザー
     * @param  Review  $review  対象レビュー
     * @return bool 削除を許可する場合はtrue
     */
    public function delete(User $user, Review $review): bool
    {
        return $this->ownsReview($user, $review);
    }

    /**
     * レビューがユーザーの所有であるか判定する。
     *
     * @param  User  $user  認証済みユーザー
     * @param  Review  $review  対象レビュー
     * @return bool ユーザーがレビューを所有している場合はtrue
     */
    private function ownsReview(User $user, Review $review): bool
    {
        return $user->id === $review->user_id;
    }
}
