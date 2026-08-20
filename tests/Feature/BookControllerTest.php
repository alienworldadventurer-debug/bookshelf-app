<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookControllerTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // 1. 一覧表示・詳細表示のテスト (正常系)
    // =========================================================================

    /**
     * 未ログインユーザーでも書籍一覧が10件のページネーションで表示されること
     */
    public function test_index_displays_paginated_books(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        Book::factory()->count(11)->create(['user_id' => $user->id]);

        // Act (実行)
        $response = $this->get(route('books.index'));

        // Assert (検証)
        $response->assertStatus(200);
        $response->assertViewHas('books');
        $this->assertCount(10, $response->viewData('books')); // 1ページ最大10件
    }

    /**
     * 書籍詳細画面にアクセスし、タイトル・著者・ISBN・ジャンル等の詳細情報が表示されること
     */
    public function test_show_displays_book_details(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $book->genres()->attach($genre->id);

        // Act (実行)
        $response = $this->get(route('books.show', $book));

        // Assert (検証)
        $response->assertStatus(200);
        $response->assertSee($book->title);
        $response->assertSee($book->author);
        $response->assertSee($book->isbn);
        $response->assertSee($genre->name);
    }

    // =========================================================================
    // 2. 新規登録 (Store) のテスト (正常系・異常系・境界値)
    // =========================================================================

    /**
     * ログインユーザーが正しい入力値で書籍を新規登録でき、一覧へリダイレクトされること
     */
    public function test_authenticated_user_can_register_book(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        // Act (実行)
        $response = $this->actingAs($user)->post(route('books.store'), [
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '1234567890123',
            'published_date' => '2026-08-17',
            'description' => 'テスト説明',
            'genres' => [$genre->id],
        ]);

        // Assert (検証)
        $response->assertRedirect(route('books.index'));
        $this->assertDatabaseHas('books', [
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '1234567890123',
        ]);
    }

    /**
     * 必須項目が空のとき、バリデーションエラーが発生すること
     */
    public function test_store_validation_fails_with_missing_required_fields(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();

        // Act (実行)
        $response = $this->actingAs($user)->post(route('books.store'), [
            'title' => '',
            'author' => '',
            'isbn' => '',
            'published_date' => '',
            'genres' => [],
        ]);

        // Assert (検証)
        $response->assertSessionHasErrors(['title', 'author', 'isbn', 'published_date', 'genres']);
    }

    /**
     * ISBNが13桁未満、または重複している場合に登録が拒否されること
     */
    public function test_store_validation_fails_with_invalid_and_duplicate_isbn(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $existingBook = Book::factory()->create([
            'isbn' => '1234567890123',
            'user_id' => $user->id,
        ]);
        $genre = Genre::factory()->create();

        // Act & Assert (無効な桁数：12桁のISBN)
        $responseInvalid = $this->actingAs($user)->post(route('books.store'), [
            'title' => 'テスト本',
            'author' => 'テスト著者',
            'isbn' => '123456789012', // 12桁
            'published_date' => '2026-08-17',
            'genres' => [$genre->id],
        ]);
        $responseInvalid->assertSessionHasErrors(['isbn']);

        // Act & Assert (重複したISBN)
        $responseDuplicate = $this->actingAs($user)->post(route('books.store'), [
            'title' => '別のテスト本',
            'author' => '別のテスト著者',
            'isbn' => '1234567890123', // 重複
            'published_date' => '2026-08-17',
            'genres' => [$genre->id],
        ]);
        $responseDuplicate->assertSessionHasErrors(['isbn']);
    }

    /**
     * タイトル、著者が255文字（境界値：上限値）であれば正常登録できること
     */
    public function test_store_boundary_success_at_255_characters(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $longString = str_repeat('あ', 255);

        // Act (実行)
        $response = $this->actingAs($user)->post(route('books.store'), [
            'title' => $longString,
            'author' => $longString,
            'isbn' => '1234567890123',
            'published_date' => '2026-08-17',
            'genres' => [$genre->id],
        ]);

        // Assert (検証)
        $response->assertRedirect(route('books.index'));
        $this->assertDatabaseHas('books', [
            'title' => $longString,
            'author' => $longString,
        ]);
    }

    /**
     * タイトル、著者が256文字以上の場合、バリデーションエラーが発生すること
     */
    public function test_store_boundary_fails_at_256_characters(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $longString = str_repeat('あ', 256);

        // Act (実行)
        $response = $this->actingAs($user)->post(route('books.store'), [
            'title' => $longString,
            'author' => $longString,
            'isbn' => '1234567890123',
            'published_date' => '2026-08-17',
            'genres' => [$genre->id],
        ]);

        // Assert (検証)
        $response->assertSessionHasErrors(['title', 'author']);
    }

    // =========================================================================
    // 3. 編集・更新 (Update) のテスト (正常系・認可エラー)
    // =========================================================================

    /**
     * 書籍の作成者本人が情報を正常に更新できること
     */
    public function test_creator_can_update_book(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $genre = Genre::factory()->create();

        // Act (実行)
        $response = $this->actingAs($user)->put(route('books.update', $book), [
            'title' => '更新後のタイトル',
            'author' => '更新後の著者',
            'isbn' => $book->isbn,
            'published_date' => '2026-08-17',
            'genres' => [$genre->id],
        ]);

        // Assert (検証)
        $response->assertRedirect(route('books.show', $book));
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '更新後のタイトル',
        ]);
    }

    /**
     * 作成者以外の他ユーザーが編集・更新を試みた場合、403 Forbidden になること
     */
    public function test_non_creator_cannot_update_book(): void
    {
        // Arrange (準備)
        $creator = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $creator->id]);
        $genre = Genre::factory()->create();

        // Act (実行)
        $response = $this->actingAs($otherUser)->put(route('books.update', $book), [
            'title' => '不正更新タイトル',
            'author' => '不正更新著者',
            'isbn' => '1111111111111',
            'published_date' => '2026-08-17',
            'genres' => [$genre->id],
        ]);

        // Assert (検証)
        $response->assertStatus(403);
    }

    // =========================================================================
    // 4. 削除 (Destroy) のテスト (正常系・認可エラー)
    // =========================================================================

    /**
     * 書籍の作成者本人が書籍を正常削除でき、中間テーブル紐付けもカスケード削除されること
     */
    public function test_creator_can_destroy_book(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $genre = Genre::factory()->create();
        $book->genres()->attach($genre->id);

        // Act (実行)
        $response = $this->actingAs($user)->delete(route('books.destroy', $book));

        // Assert (検証)
        $response->assertRedirect(route('books.index'));
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseMissing('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $genre->id,
        ]);
    }

    /**
     * 作成者以外の他ユーザーが書籍を削除しようとした場合、403 Forbidden になること
     */
    public function test_non_creator_cannot_destroy_book(): void
    {
        // Arrange (準備)
        $creator = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $creator->id]);

        // Act (実行)
        $response = $this->actingAs($otherUser)->delete(route('books.destroy', $book));

        // Assert (検証)
        $response->assertStatus(403);
        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }
}
