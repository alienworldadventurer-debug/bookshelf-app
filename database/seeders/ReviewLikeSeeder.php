<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewLikeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $reviews = Review::all();

        if ($users->isEmpty() || $reviews->isEmpty()) {
            return;
        }

        foreach ($reviews as $review) {
            $likeCount = rand(0, 3);

            if ($likeCount === 0) {
                continue;
            }

            // 自分の投稿したレビュー以外をいいね対象とする（投稿者本人を除外）
            $candidateUsers = $users->filter(function ($user) use ($review) {
                return $user->id !== $review->user_id;
            });

            if ($candidateUsers->isEmpty()) {
                continue;
            }

            // いいねする実人数を決定
            $actualLikeCount = min($likeCount, $candidateUsers->count());
            $likerIds = $candidateUsers->random($actualLikeCount)->pluck('id')->toArray();

            $review->likedByUsers()->syncWithoutDetaching($likerIds);
        }
    }
}
