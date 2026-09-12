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

    /**
     * ログインユーザーがレビューを投稿でき、書籍詳細へリダイレクトされることを検証する。
     *
     * @return void レビューの保存内容とリダイレクト先を確認する
     */
    public function test_authenticated_user_can_post_review(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('reviews.store', $book), [
            'rating' => 5,
            'comment' => '素晴らしい本でした！',
        ]);

        $response->assertRedirect(route('books.show', $book));
        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => '素晴らしい本でした！',
        ]);
    }

    /**
     * 評価が未入力または1から5の範囲外の場合にバリデーションエラーになることを検証する。
     *
     * @return void 必須、下限超過、上限超過の各エラーを確認する
     */
    public function test_review_store_validation_fails_with_invalid_rating(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $responseRequired = $this->actingAs($user)->post(route('reviews.store', $book), [
            'rating' => '',
            'comment' => '評価なし',
        ]);
        $responseRequired->assertSessionHasErrors(['rating']);

        $responseMin = $this->actingAs($user)->post(route('reviews.store', $book), [
            'rating' => 0,
            'comment' => '評価0',
        ]);
        $responseMin->assertSessionHasErrors(['rating']);

        $responseMax = $this->actingAs($user)->post(route('reviews.store', $book), [
            'rating' => 6,
            'comment' => '評価6',
        ]);
        $responseMax->assertSessionHasErrors(['rating']);
    }

    /**
     * コメントが上限の1000文字であれば正常に登録できることを検証する。
     *
     * @return void 1000文字のコメントが保存されることを確認する
     */
    public function test_review_store_boundary_success_at_1000_characters(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $longComment = str_repeat('あ', 1000);

        $response = $this->actingAs($user)->post(route('reviews.store', $book), [
            'rating' => 4,
            'comment' => $longComment,
        ]);

        $response->assertRedirect(route('books.show', $book));
        $this->assertDatabaseHas('reviews', [
            'rating' => 4,
            'comment' => $longComment,
        ]);
    }

    /**
     * コメントが1001文字以上の場合にバリデーションエラーになることを検証する。
     *
     * @return void コメント長の上限超過エラーを確認する
     */
    public function test_review_store_boundary_fails_at_1001_characters(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $longComment = str_repeat('あ', 1001);

        $response = $this->actingAs($user)->post(route('reviews.store', $book), [
            'rating' => 4,
            'comment' => $longComment,
        ]);

        $response->assertSessionHasErrors(['comment']);
    }

    /**
     * レビュー投稿者本人がレビューを更新できることを検証する。
     *
     * @return void 更新内容の保存と書籍詳細へのリダイレクトを確認する
     */
    public function test_creator_can_update_review(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $review = Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 3,
            'comment' => '普通でした',
        ]);

        $response = $this->actingAs($user)->put(route('reviews.update', $review), [
            'rating' => 4,
            'comment' => '読み返すと良かったです！',
        ]);

        $response->assertRedirect(route('books.show', $book));
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 4,
            'comment' => '読み返すと良かったです！',
        ]);
    }

    /**
     * 投稿者以外のユーザーによるレビュー更新が403 Forbiddenになることを検証する。
     *
     * @return void レビュー更新の認可制限を確認する
     */
    public function test_non_creator_cannot_update_review(): void
    {
        $creator = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $creator->id]);
        $review = Review::factory()->create([
            'user_id' => $creator->id,
            'book_id' => $book->id,
        ]);

        $response = $this->actingAs($otherUser)->put(route('reviews.update', $review), [
            'rating' => 5,
            'comment' => '勝手に更新',
        ]);

        $response->assertStatus(403);
    }

    /**
     * レビュー投稿者本人がレビューを削除できることを検証する。
     *
     * @return void 削除処理とデータベースからの消去を確認する
     */
    public function test_creator_can_destroy_review(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $review = Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $response = $this->actingAs($user)->delete(route('reviews.destroy', $review));

        $response->assertRedirect(route('books.show', $book));
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    /**
     * 投稿者以外のユーザーによるレビュー削除が403 Forbiddenになることを検証する。
     *
     * @return void レビュー削除の認可制限とデータ保持を確認する
     */
    public function test_non_creator_cannot_destroy_review(): void
    {
        $creator = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $creator->id]);
        $review = Review::factory()->create([
            'user_id' => $creator->id,
            'book_id' => $book->id,
        ]);

        $response = $this->actingAs($otherUser)->delete(route('reviews.destroy', $review));

        $response->assertStatus(403);
        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }
}
