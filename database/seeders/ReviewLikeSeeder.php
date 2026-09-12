<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewLikeSeeder extends Seeder
{
    /**
     * 各レビューに対する初期いいねデータを登録する。
     *
     * レビュー投稿者本人を除外し、レビューごとに最大3人のユーザーを
     * ランダムに選択していいねを紐付ける。
     */
    public function run(): void
    {
        $users = User::all();
        $reviews = Review::all();

        if ($users->isEmpty() || $reviews->isEmpty()) {
            return;
        }

        $reviews->each(function (Review $review) use ($users): void {
            $likeCount = rand(0, 3);

            if ($likeCount === 0) {
                return;
            }

            $candidateUsers = $users->filter(function (User $user) use ($review): bool {
                return $user->id !== $review->user_id;
            });

            if ($candidateUsers->isEmpty()) {
                return;
            }

            $actualLikeCount = min($likeCount, $candidateUsers->count());
            $likerIds = $candidateUsers
                ->random($actualLikeCount)
                ->pluck('id')
                ->all();

            $review->likedByUsers()->syncWithoutDetaching($likerIds);
        });
    }
}
