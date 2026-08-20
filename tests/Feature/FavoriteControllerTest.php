<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteControllerTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // 1. お気に入り一覧のテスト (正常系・ページネーション・認可)
    // =========================================================================

    /**
     * ログインユーザーがお気に入り一覧画面を表示でき、自分がお気に入りに登録した書籍のみが表示されること
     */
    public function test_authenticated_user_can_view_favorites_index(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // 自分の本とお気に入り登録
        $myFavBook = Book::factory()->create(['user_id' => $user->id]);
        $user->favoriteBooks()->attach($myFavBook->id);

        // 他人の本とお気に入り登録
        $otherFavBook = Book::factory()->create(['user_id' => $otherUser->id]);
        $otherUser->favoriteBooks()->attach($otherFavBook->id);

        // Act (実行)
        $response = $this->actingAs($user)->get(route('favorites.index'));

        // Assert (検証)
        $response->assertStatus(200);
        $response->assertViewHas('books');

        // 自分のお気に入り書籍が表示されていること
        $response->assertSee($myFavBook->title);
        // 他人のお気に入り書籍は表示されていないこと
        $response->assertDontSee($otherFavBook->title);

        $viewBooks = $response->viewData('books');
        $this->assertCount(1, $viewBooks);
        $this->assertTrue($viewBooks->contains($myFavBook));
    }

    /**
     * お気に入り一覧画面で、登録された書籍が1ページあたり最大10件でページネーション表示されること
     */
    public function test_favorites_index_is_paginated_to_10_items(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();

        // お気に入り書籍をあえて「11冊」作成し、お気に入りに登録する
        $books = Book::factory()->count(11)->create(['user_id' => $user->id]);
        foreach ($books as $book) {
            $user->favoriteBooks()->attach($book->id);
        }

        // Act (実行)
        $response = $this->actingAs($user)->get(route('favorites.index'));

        // Assert (検証)
        $response->assertStatus(200);
        $response->assertViewHas('books');

        // 1ページ目の表示件数が最大「10件」に制限されていることを検証
        $this->assertCount(10, $response->viewData('books'));
    }

    /**
     * 未ログイン(ゲスト)ユーザーがお気に入り一覧画面にアクセスした際、ログイン画面へリダイレクトされること
     */
    public function test_guest_user_cannot_view_favorites_index(): void
    {
        // Act (実行)
        $response = $this->get(route('favorites.index'));

        // Assert (検証)
        $response->assertRedirect(route('login'));
    }

    // =========================================================================
    // 2. お気に入り登録・解除 (Toggle) のテスト (正常系・アクセス制限)
    // =========================================================================

    /**
     * ログインユーザーがお気に入りボタンを押すことで、お気に入り登録（追加）が正常に行われること
     */
    public function test_authenticated_user_can_add_book_to_favorites(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        // まだお気に入りに登録されていない状態
        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        // Act (実行) - 書籍詳細画面からリクエストしたと仮定してfromを指定
        $response = $this->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book));

        // Assert (検証)
        $response->assertRedirect(route('books.show', $book)); // リダイレクトバックされること

        // データベースにお気に入りレコードが追加されていることを確認
        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    /**
     * すでにお気に入りに登録されている書籍に対してボタンを押すと、お気に入り解除（削除）されること
     */
    public function test_authenticated_user_can_remove_book_from_favorites(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        // 最初にお気に入り登録しておく
        $user->favoriteBooks()->attach($book->id);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        // Act (実行)
        $response = $this->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book));

        // Assert (検証)
        $response->assertRedirect(route('books.show', $book));

        // データベースからお気に入りレコードが消えていることを確認
        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    /**
     * 未ログイン(ゲスト)ユーザーがお気に入り登録(Toggle)を試みた際、ログイン画面へリダイレクトされること
     */
    public function test_guest_user_cannot_toggle_favorites(): void
    {
        // Arrange (準備)
        $book = Book::factory()->create();

        // Act (実行)
        $response = $this->post(route('favorites.toggle', $book));

        // Assert (検証)
        $response->assertRedirect(route('login'));
    }
}
