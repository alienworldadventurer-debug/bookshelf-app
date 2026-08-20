<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiBookControllerTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // 1. 書籍一覧取得API (GET /api/v1/books) のテスト
    // =========================================================================

    /**
     * 正常系: 書籍一覧が正しいJSON構造で、平均評価・ジャンル情報・レビュー件数を含んで取得できること
     */
    public function test_can_get_book_list_with_correct_structure(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $book->genres()->attach($genre->id);

        Review::factory()->create([
            'book_id' => $book->id,
            'user_id' => $user->id,
            'rating' => 4,
        ]);

        // Act (実行) - 教材の標準に合わせ、URLを直接指定してリクエスト
        $response = $this->getJson('/api/v1/books');

        // Assert (検証)
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'author',
                    'isbn',
                    'published_date',
                    'description',
                    'image_url',
                    'genres' => [
                        '*' => [
                            'id',
                            'name',
                        ],
                    ],
                    'reviews_avg_rating',
                    'reviews_count',
                    'created_at',
                    'updated_at',
                ],
            ],
            'links' => [
                'first',
                'last',
                'prev',
                'next',
            ],
            'meta' => [
                'current_page',
                'from',
                'last_page',
                'per_page',
                'to',
                'total',
            ],
        ]);

        $response->assertJsonFragment([
            'id' => $book->id,
            'title' => $book->title,
            'reviews_avg_rating' => 4.0,
            'reviews_count' => 1,
        ]);
    }

    /**
     * 正常系: キーワード（部分一致）およびジャンルIDによるフィルタリングが正しく機能すること
     */
    public function test_can_filter_book_list_by_keyword_and_genre(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $genreA = Genre::factory()->create(['name' => '小説']);
        $genreB = Genre::factory()->create(['name' => '技術書']);

        $bookMatch = Book::factory()->create([
            'user_id' => $user->id,
            'title' => 'Laravel入門',
            'author' => '山田太郎',
        ]);
        $bookMatch->genres()->attach($genreA->id);

        $bookUnmatch = Book::factory()->create([
            'user_id' => $user->id,
            'title' => 'Java入門',
            'author' => '鈴木一郎',
        ]);
        $bookUnmatch->genres()->attach($genreB->id);

        // Act (実行) - 検索クエリをURLパラメーターとして直書き
        $response = $this->getJson("/api/v1/books?keyword=Laravel&genre={$genreA->id}");

        // Assert (検証)
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['id' => $bookMatch->id]);
        $response->assertJsonMissing(['id' => $bookUnmatch->id]);
    }

    /**
     * 正常系: 指定した件数（per_page）およびページ（page）でのページネーションが正しく機能すること
     */
    public function test_can_get_paginated_book_list(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        Book::factory()->count(15)->create(['user_id' => $user->id]);

        // Act (実行) - ページネーションパラメータを直書き
        $response = $this->getJson('/api/v1/books?per_page=5&page=2');

        // Assert (検証)
        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
        $response->assertJsonPath('meta.current_page', 2);
        $response->assertJsonPath('meta.per_page', 5);
        $response->assertJsonPath('meta.total', 15);
    }

    /**
     * 異常系: 無効な絞り込みパラメータ（型エラー）が指定された際にHTTP 422となり、日本語のエラーが返ること
     */
    public function test_get_book_list_validation_error(): void
    {
        // Act (実行)
        $response = $this->getJson('/api/v1/books?genre=invalid-string&page=invalid-string');

        // Assert (検証)
        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'errors' => [
                'genre',
                'page',
            ],
        ]);
        $response->assertJsonFragment(['message' => '入力内容に不備があります。']);
    }

    // =========================================================================
    // 2. 書籍詳細取得API (GET /api/v1/books/{id}) のテスト
    // =========================================================================

    /**
     * 正常系: 指定IDの書籍情報、ジャンル、レビュー一覧（投稿者名含む）が正しいJSON構造で返ること
     */
    public function test_can_get_book_detail_with_correct_structure(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $book->genres()->attach($genre->id);

        $reviewer = User::factory()->create(['name' => 'レビュー太郎']);
        // ✨ 未使用だった「$review =」をクリーンアップ！
        Review::factory()->create([
            'book_id' => $book->id,
            'user_id' => $reviewer->id,
            'rating' => 5,
            'comment' => '素晴らしい本です。',
        ]);

        // Act (実行)
        $response = $this->getJson("/api/v1/books/{$book->id}");

        // Assert (検証)
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'title',
                'author',
                'isbn',
                'published_date',
                'description',
                'image_url',
                'genres' => [
                    '*' => [
                        'id',
                        'name',
                    ],
                ],
                'reviews_avg_rating',
                'reviews_count',
                'reviews' => [
                    '*' => [
                        'id',
                        'user_name',
                        'rating',
                        'comment',
                        'created_at',
                    ],
                ],
                'created_at',
                'updated_at',
            ],
        ]);

        $response->assertJsonFragment([
            'id' => $book->id,
            'reviews_avg_rating' => 5.0,
            'reviews_count' => 1,
            'user_name' => 'レビュー太郎',
            'rating' => 5,
            'comment' => '素晴らしい本です。',
        ]);
    }

    /**
     * 異常系: 存在しない書籍IDを指定した際、HTTP 404と指定エラーメッセージJSONが返ること
     */
    public function test_get_book_detail_not_found(): void
    {
        // Act (実行)
        $response = $this->getJson('/api/v1/books/99999');

        // Assert (検証)
        $response->assertStatus(404);
        $response->assertJson([
            'message' => '指定された書籍が見つかりません。',
        ]);
    }

    // =========================================================================
    // 3. 書籍登録API (POST /api/v1/books) のテスト
    // =========================================================================

    /**
     * 正常系: 有効なパラメータを送信した際、書籍が正常登録され、HTTP 201 Created と作成されたデータが返ること
     */
    public function test_can_store_book(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $params = [
            'title' => '新刊タイトル',
            'author' => '新刊著者',
            'isbn' => '9784101024011',
            'published_date' => '2026-08-01',
            'description' => '新刊の説明です。',
            'image_url' => 'https://example.com/cover.png',
            'genres' => [$genre->id],
            'user_id' => $user->id,
        ];

        // Act (実行)
        $response = $this->postJson('/api/v1/books', $params);

        // Assert (検証)
        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'title',
                'author',
                'isbn',
                'genres',
            ],
        ]);

        $bookId = $response->json('data.id');
        $this->assertDatabaseHas('books', [
            'id' => $bookId,
            'title' => '新刊タイトル',
            'isbn' => '9784101024011',
        ]);
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $bookId,
            'genre_id' => $genre->id,
        ]);
    }

    /**
     * 異常系: 必須項目が欠落している場合、HTTP 422と日本語エラーメッセージが返ること
     */
    public function test_store_book_validation_error_missing_fields(): void
    {
        // Act (実行)
        $response = $this->postJson('/api/v1/books', []);

        // Assert (検証)
        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => '入力内容に不備があります。']);
        $response->assertJsonValidationErrors([
            'title',
            'author',
            'isbn',
            'published_date',
            'genres',
            'user_id',
        ]);
    }

    /**
     * 異常系: 存在しない user_id や genres.0 を指定した場合、HTTP 422とエラーメッセージが返ること
     */
    public function test_store_book_validation_error_master_existence(): void
    {
        // ✨ 未使用だった「$genre = Genre::factory()->create();」を完全削除してクリーンアップ！

        $params = [
            'title' => 'マスタ検証用',
            'author' => '検証著者',
            'isbn' => '9784101024011',
            'published_date' => '2026-08-01',
            'genres' => [99999],
            'user_id' => 99999,
        ];

        // Act (実行)
        $response = $this->postJson('/api/v1/books', $params);

        // Assert (検証)
        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'user_id',
            'genres.0',
        ]);
    }

    /**
     * 異常系: 重複するISBNを指定して登録しようとした場合、HTTP 422と指定の日本語エラーが返ること
     */
    public function test_store_book_validation_error_duplicate_isbn(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        Book::factory()->create(['isbn' => '9784101024011', 'user_id' => $user->id]);

        $params = [
            'title' => '重複ISBN検証本',
            'author' => '重複著者',
            'isbn' => '9784101024011',
            'published_date' => '2026-08-01',
            'genres' => [$genre->id],
            'user_id' => $user->id,
        ];

        // Act (実行)
        $response = $this->postJson('/api/v1/books', $params);

        // Assert (検証)
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['isbn']);
    }

    // =========================================================================
    // 4. 書籍更新API (PUT /api/v1/books/{id}) のテスト
    // =========================================================================

    /**
     * 正常系: 正しいデータで更新した際、HTTP 200 OK と更新後のデータが返り、中間テーブルも同期されること
     */
    public function test_can_update_book(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $genreOld = Genre::factory()->create();
        $genreNew = Genre::factory()->create();

        $book = Book::factory()->create(['user_id' => $user->id]);
        $book->genres()->attach($genreOld->id);

        $params = [
            'title' => '更新後のタイトル',
            'author' => '更新後の著者',
            'isbn' => $book->isbn,
            'published_date' => '2026-08-02',
            'description' => '更新後の説明。',
            'genres' => [$genreNew->id],
        ];

        // Act (実行)
        $response = $this->putJson("/api/v1/books/{$book->id}", $params);

        // Assert (検証)
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'title' => '更新後のタイトル',
            'author' => '更新後の著者',
        ]);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '更新後のタイトル',
        ]);
        $this->assertDatabaseMissing('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $genreOld->id,
        ]);
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $genreNew->id,
        ]);
    }

    /**
     * 異常系: 他の書籍が使っているISBNを指定して更新しようとした場合、HTTP 422となること
     */
    public function test_update_book_validation_error_duplicate_isbn(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        // ✨ 未使用だった「$bookA =」をクリーンアップ！
        Book::factory()->create(['isbn' => '9784101010014', 'user_id' => $user->id]);
        $bookB = Book::factory()->create(['isbn' => '9784101024011', 'user_id' => $user->id]);

        $params = [
            'title' => '更新タイトル',
            'author' => '更新著者',
            'isbn' => '9784101010014',
            'published_date' => '2026-08-02',
            'genres' => [$genre->id],
        ];

        // Act (実行)
        $response = $this->putJson("/api/v1/books/{$bookB->id}", $params);

        // Assert (検証)
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['isbn']);
    }

    /**
     * 異常系: 存在しない書籍IDを指定して更新しようとした場合、HTTP 404と指定のエラーメッセージが返ること
     */
    public function test_update_book_not_found(): void
    {
        // Arrange (準備)
        $genre = Genre::factory()->create();
        $params = [
            'title' => '更新タイトル',
            'author' => '更新著者',
            'isbn' => '9784101024011',
            'published_date' => '2026-08-02',
            'genres' => [$genre->id],
        ];

        // Act (実行)
        $response = $this->putJson('/api/v1/books/99999', $params);

        // Assert (検証)
        $response->assertStatus(404);
        $response->assertJson([
            'message' => '指定された書籍が見つかりません。',
        ]);
    }

    // =========================================================================
    // 5. 書籍削除API (DELETE /api/v1/books/{id}) のテスト
    // =========================================================================

    /**
     * 正常系: 書籍を正常削除でき、HTTP 200 とメッセージJSONが返り、関連データも連動して削除されること
     */
    public function test_can_delete_book(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $book->genres()->attach($genre->id);
        $user->favoriteBooks()->attach($book->id);

        Review::factory()->create([
            'book_id' => $book->id,
            'user_id' => $user->id,
            'rating' => 4,
        ]);

        $this->assertDatabaseHas('books', ['id' => $book->id]);

        // Act (実行)
        $response = $this->deleteJson("/api/v1/books/{$book->id}");

        // Assert (検証)
        $response->assertStatus(200);
        $response->assertJson([
            'message' => '書籍を削除しました。',
        ]);

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('favorites', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('reviews', ['book_id' => $book->id]);
    }

    /**
     * 異常系: 存在しない書籍IDを指定して削除を試みた際、HTTP 404と指定エラーメッセージJSONが返ること
     */
    public function test_delete_book_not_found(): void
    {
        // Act (実行)
        $response = $this->deleteJson('/api/v1/books/99999');

        // Assert (検証)
        $response->assertStatus(404);
        $response->assertJson([
            'message' => '指定された書籍が見つかりません。',
        ]);
    }
}