<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ログインユーザーがお気に入り一覧を表示でき、自分の登録した書籍だけが表示されることを検証する。
     *
     * @return void お気に入り一覧の表示内容を確認する
     */
    public function test_authenticated_user_can_view_favorites_index(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $myFavBook = Book::factory()->create(['user_id' => $user->id]);
        $user->favoriteBooks()->attach($myFavBook->id);

        $otherFavBook = Book::factory()->create(['user_id' => $otherUser->id]);
        $otherUser->favoriteBooks()->attach($otherFavBook->id);

        $response = $this->actingAs($user)->get(route('favorites.index'));

        $response->assertStatus(200);
        $response->assertViewHas('books');
        $response->assertSee($myFavBook->title);
        $response->assertDontSee($otherFavBook->title);

        $viewBooks = $response->viewData('books');
        $this->assertCount(1, $viewBooks);
        $this->assertTrue($viewBooks->contains($myFavBook));
    }

    /**
     * お気に入り一覧が1ページあたり最大10件でページネーション表示されることを検証する。
     *
     * @return void 1ページ目に表示される書籍数を確認する
     */
    public function test_favorites_index_is_paginated_to_10_items(): void
    {
        $user = User::factory()->create();

        $books = Book::factory()->count(11)->create(['user_id' => $user->id]);
        foreach ($books as $book) {
            $user->favoriteBooks()->attach($book->id);
        }

        $response = $this->actingAs($user)->get(route('favorites.index'));

        $response->assertStatus(200);
        $response->assertViewHas('books');
        $this->assertCount(10, $response->viewData('books'));
    }

    /**
     * 未ログインユーザーがお気に入り一覧にアクセスするとログイン画面へリダイレクトされることを検証する。
     *
     * @return void ゲストアクセス時のリダイレクト先を確認する
     */
    public function test_guest_user_cannot_view_favorites_index(): void
    {
        $response = $this->get(route('favorites.index'));

        $response->assertRedirect(route('login'));
    }

    /**
     * ログインユーザーがお気に入り切り替えを実行すると書籍が登録されることを検証する。
     *
     * @return void お気に入りレコードの追加とリダイレクト先を確認する
     */
    public function test_authenticated_user_can_add_book_to_favorites(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $response = $this->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book));

        $response->assertRedirect(route('books.show', $book));
        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    /**
     * 登録済みの書籍にお気に入り切り替えを実行すると登録が解除されることを検証する。
     *
     * @return void お気に入りレコードの削除とリダイレクト先を確認する
     */
    public function test_authenticated_user_can_remove_book_from_favorites(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $user->favoriteBooks()->attach($book->id);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $response = $this->actingAs($user)
            ->from(route('books.show', $book))
            ->post(route('favorites.toggle', $book));

        $response->assertRedirect(route('books.show', $book));
        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    /**
     * 未ログインユーザーがお気に入り切り替えを試みるとログイン画面へリダイレクトされることを検証する。
     *
     * @return void ゲストアクセス時のリダイレクト先を確認する
     */
    public function test_guest_user_cannot_toggle_favorites(): void
    {
        $book = Book::factory()->create();

        $response = $this->post(route('favorites.toggle', $book));

        $response->assertRedirect(route('login'));
    }
}
