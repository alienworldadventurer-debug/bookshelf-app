<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // 1. レビュー投稿 (Store) のテスト (正常系・異常系・境界値)
    // =========================================================================

    /**
     * ログインユーザーが正常にレビューを投稿でき、書籍詳細へリダイレクトされること
     */
    public function test_authenticated_user_can_post_review(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        // Act (実行)
        $response = $this->actingAs($user)->post(route('reviews.store', $book), [
            'rating' => 5,
            'comment' => '素晴らしい本でした！',
        ]);

        // Assert (検証)
        $response->assertRedirect(route('books.show', $book)); // 書籍詳細へ戻る
        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => '素晴らしい本でした！',
        ]);
    }

    /**
     * 評価(rating)が未入力、または範囲外の場合にバリデーションエラーが発生すること
     */
    public function test_review_store_validation_fails_with_invalid_rating(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        // Act & Assert ①：必須エラー (空)
        $responseRequired = $this->actingAs($user)->post(route('reviews.store', $book), [
            'rating' => '',
            'comment' => '評価なし',
        ]);
        $responseRequired->assertSessionHasErrors(['rating']);

        // Act & Assert ②：下限境界外エラー (0は範囲外：1〜5)
        $responseMin = $this->actingAs($user)->post(route('reviews.store', $book), [
            'rating' => 0,
            'comment' => '評価0',
        ]);
        $responseMin->assertSessionHasErrors(['rating']);

        // Act & Assert ③：上限境界外エラー (6は範囲外：1〜5)
        $responseMax = $this->actingAs($user)->post(route('reviews.store', $book), [
            'rating' => 6,
            'comment' => '評価6',
        ]);
        $responseMax->assertSessionHasErrors(['rating']);
    }

    /**
     * コメントが1000文字（境界値：上限値）であれば正常登録できること
     */
    public function test_review_store_boundary_success_at_1000_characters(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $longComment = str_repeat('あ', 1000);

        // Act (実行)
        $response = $this->actingAs($user)->post(route('reviews.store', $book), [
            'rating' => 4,
            'comment' => $longComment,
        ]);

        // Assert (検証)
        $response->assertRedirect(route('books.show', $book));
        $this->assertDatabaseHas('reviews', [
            'rating' => 4,
            'comment' => $longComment,
        ]);
    }

    /**
     * コメントが1001文字以上の場合、バリデーションエラーが発生すること
     */
    public function test_review_store_boundary_fails_at_1001_characters(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $longComment = str_repeat('あ', 1001);

        // Act (実行)
        $response = $this->actingAs($user)->post(route('reviews.store', $book), [
            'rating' => 4,
            'comment' => $longComment,
        ]);

        // Assert (検証)
        $response->assertSessionHasErrors(['comment']);
    }

    // =========================================================================
    // 2. レビュー編集・更新 (Update) のテスト (正常系・認可エラー)
    // =========================================================================

    /**
     * 投稿者本人がレビューを正常に更新できること
     */
    public function test_creator_can_update_review(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $review = Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 3,
            'comment' => '普通でした',
        ]);

        // Act (実行)
        $response = $this->actingAs($user)->put(route('reviews.update', $review), [
            'rating' => 4,
            'comment' => '読み返すと良かったです！',
        ]);

        // Assert (検証)
        $response->assertRedirect(route('books.show', $book));
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 4,
            'comment' => '読み返すと良かったです！',
        ]);
    }

    /**
     * 投稿者以外の他ユーザーが編集・更新を試みた場合、403 Forbidden になること
     */
    public function test_non_creator_cannot_update_review(): void
    {
        // Arrange (準備)
        $creator = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $creator->id]);
        $review = Review::factory()->create([
            'user_id' => $creator->id,
            'book_id' => $book->id,
        ]);

        // Act (実行)
        $response = $this->actingAs($otherUser)->put(route('reviews.update', $review), [
            'rating' => 5,
            'comment' => '勝手に更新',
        ]);

        // Assert (検証)
        $response->assertStatus(403); // 認可ポリシー（Policy）による制限
    }

    // =========================================================================
    // 3. レビュー削除 (Destroy) のテスト (正常系・認可エラー)
    // =========================================================================

    /**
     * 投稿者本人がレビューを削除できること
     */
    public function test_creator_can_destroy_review(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $review = Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        // Act (実行)
        $response = $this->actingAs($user)->delete(route('reviews.destroy', $review));

        // Assert (検証)
        $response->assertRedirect(route('books.show', $book));
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    /**
     * 投稿者以外の他ユーザーがレビューの削除を試みた場合、403 Forbidden になること
     */
    public function test_non_creator_cannot_destroy_review(): void
    {
        // Arrange (準備)
        $creator = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $creator->id]);
        $review = Review::factory()->create([
            'user_id' => $creator->id,
            'book_id' => $book->id,
        ]);

        // Act (実行)
        $response = $this->actingAs($otherUser)->delete(route('reviews.destroy', $review));

        // Assert (検証)
        $response->assertStatus(403);
        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }
}
