<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewLikeControllerTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // 1. いいねトグル (Toggle) のテスト (正常系)
    // =========================================================================

    /**
     * ログインユーザーが他人のレビューに対していいねを追加（登録）できること
     */
    public function test_authenticated_user_can_like_other_user_review(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $otherUser->id]);
        $review = Review::factory()->create([
            'book_id' => $book->id,
            'user_id' => $otherUser->id,
            'rating' => 4,
        ]);

        // まだいいねしていない状態（事前アサーション）
        $this->assertDatabaseMissing('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);

        // Act (実行) - 書籍詳細画面からリクエストしたと仮定してfromを指定
        $response = $this->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('reviews.like', $review));

        // Assert (検証)
        $response->assertRedirect(route('books.show', $book)); // リダイレクトバックされること

        // データベースにいいねレコードが追加されていることを確認
        $this->assertDatabaseHas('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
    }

    /**
     * すでにいいねしているレビューに対して再度いいねボタンを押すと、いいねが解除（削除）されること
     */
    public function test_authenticated_user_can_unlike_review(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $otherUser->id]);
        $review = Review::factory()->create([
            'book_id' => $book->id,
            'user_id' => $otherUser->id,
            'rating' => 4,
        ]);

        // 最初にお気に入り（いいね）登録しておく
        $user->likedReviews()->attach($review->id);

        $this->assertDatabaseHas('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);

        // Act (実行)
        $response = $this->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('reviews.like', $review));

        // Assert (検証)
        $response->assertRedirect(route('books.show', $book));

        // データベースからいいねレコードが消えていることを確認
        $this->assertDatabaseMissing('review_likes', [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ]);
    }

    // =========================================================================
    // 2. アクセス制限のテスト (異常系)
    // =========================================================================

    /**
     * 未ログイン（ゲスト）ユーザーがいいねトグルを試みた際、ログイン画面へリダイレクトされること
     */
    public function test_guest_user_cannot_toggle_review_like(): void
    {
        // Arrange (準備)
        $review = Review::factory()->create();

        // Act (実行)
        $response = $this->post(route('reviews.like', $review));

        // Assert (検証)
        $response->assertRedirect(route('login'));
    }
}
