<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiBookControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ==========================================
     * GET（読み取り系）：認証不要のエンドポイントテスト
     * ==========================================
     */
    public function test_can_get_book_list_with_correct_structure()
    {
        $genre = Genre::factory()->create();
        Book::factory()->count(3)->hasAttached($genre)->create();

        $response = $this->getJson('/api/v1/books');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'author',
                        'isbn',
                        'published_date',
                        'description',
                        'image_url',
                        'genres',
                        'reviews_avg_rating',
                        'reviews_count',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    public function test_can_filter_book_list_by_keyword_and_genre()
    {
        $genre1 = Genre::factory()->create(['name' => '小説']);
        $genre2 = Genre::factory()->create(['name' => 'ビジネス']);

        $book1 = Book::factory()->hasAttached($genre1)->create(['title' => 'ターゲット本']);
        Book::factory()->hasAttached($genre2)->create(['title' => '無関係な本']);

        $response = $this->getJson("/api/v1/books?keyword=ターゲット&genre={$genre1->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $book1->id]);
    }

    public function test_can_get_paginated_book_list()
    {
        $genre = Genre::factory()->create();
        Book::factory()->count(11)->hasAttached($genre)->create();

        $response = $this->getJson('/api/v1/books?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
            ]);
    }

    public function test_get_book_list_validation_error()
    {
        $response = $this->getJson('/api/v1/books?genre=invalid&per_page=101');

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['genre', 'per_page']);
    }

    public function test_can_get_book_detail_with_correct_structure()
    {
        $genre = Genre::factory()->create();
        $book = Book::factory()->hasAttached($genre)->create();

        $response = $this->getJson("/api/v1/books/{$book->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'author',
                    'isbn',
                    'published_date',
                    'description',
                    'image_url',
                    'genres',
                    'reviews_avg_rating',
                    'reviews_count',
                    'reviews',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_get_book_detail_not_found()
    {
        $response = $this->getJson('/api/v1/books/99999');

        $response->assertStatus(404)
            ->assertJson([
                'message' => '指定された書籍が見つかりません。',
            ]);
    }

    /**
     * ==========================================
     * POST/PUT/DELETE（書き込み系）：要Sanctum認証テスト
     * ==========================================
     */
    public function test_can_store_book()
    {
        // 1. テストユーザーを作成
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        // 2. Sanctum疑似ログインを有効化
        Sanctum::actingAs($user);

        // 3. user_id パラメータは送信しない
        $response = $this->postJson('/api/v1/books', [
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9784101010014',
            'published_date' => '2023-01-01',
            'description' => 'テスト説明',
            'image_url' => 'https://example.com/image.jpg',
            'genres' => [$genre->id],
        ]);

        // 4. 201 Created レスポンスと、作成者ID(user_id)にログイン中のユーザーIDがセットされていることを確認
        $response->assertStatus(201);
        $this->assertDatabaseHas('books', [
            'title' => 'テスト書籍',
            'user_id' => $user->id, // 👈 ログインユーザーIDが自動設定されているか検証
        ]);
    }

    public function test_store_book_validation_error_missing_fields()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user); // 👈 ログイン状態にする（これがないと401エラーになる）

        $response = $this->postJson('/api/v1/books', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'author', 'genres']);
    }

    public function test_store_book_validation_error_master_existence()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user); // 👈 ログイン状態にする

        $response = $this->postJson('/api/v1/books', [
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'genres' => [99999], // 存在しないジャンルID
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['genres.0']);
    }

    public function test_store_book_validation_error_duplicate_isbn()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user); // 👈 ログイン状態にする

        Book::factory()->create(['isbn' => '9784101010014']);

        $response = $this->postJson('/api/v1/books', [
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9784101010014', // 重複するISBN
            'genres' => [Genre::factory()->create()->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['isbn']);
    }

    public function test_can_update_book()
    {
        // 1. 本の登録者（オーナー）を作成してログイン
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        // 2. その登録者に紐づく本を作成
        $genre = Genre::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);

        $response = $this->putJson("/api/v1/books/{$book->id}", [
            'title' => '更新後のタイトル',
            'author' => '更新後の著者',
            'isbn' => '9784101010021',
            'published_date' => '2023-02-01',
            'description' => '更新後の説明',
            'image_url' => 'https://example.com/updated.jpg',
            'genres' => [$genre->id],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => '更新後のタイトル',
        ]);
    }

    public function test_update_book_validation_error_duplicate_isbn()
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner); // 👈 ログイン状態にする

        $genre = Genre::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id, 'isbn' => '9784101010014']);
        Book::factory()->create(['isbn' => '9784101010021']); // 他の本のISBN

        $response = $this->putJson("/api/v1/books/{$book->id}", [
            'title' => '更新タイトル',
            'author' => '更新著者',
            'isbn' => '9784101010021', // 他の本と重複するISBN
            'genres' => [$genre->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['isbn']);
    }

    public function test_update_book_not_found()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user); // 👈 ログイン状態にする

        $genre = Genre::factory()->create();

        $response = $this->putJson('/api/v1/books/99999', [
            'title' => '更新タイトル',
            'author' => '更新著者',
            'genres' => [$genre->id],
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => '指定された書籍が見つかりません。',
            ]);
    }

    public function test_can_delete_book()
    {
        // 1. 本の所有者を作成してログイン
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        // 2. その所有者に紐づく本を作成
        $book = Book::factory()->create(['user_id' => $owner->id]);

        $response = $this->deleteJson("/api/v1/books/{$book->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => '書籍を削除しました。',
            ]);

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_delete_book_not_found()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user); // 👈 ログイン状態にする

        $response = $this->deleteJson('/api/v1/books/99999');

        $response->assertStatus(404)
            ->assertJson([
                'message' => '指定された書籍が見つかりません。',
            ]);
    }

    /**
     * ==========================================
     * 【新規追加】応用フェーズ用の異常系テスト（未認証 / 未認可）
     * ==========================================
     */

    /**
     * 未認証（ゲスト）が書き込み系エンドポイントにアクセスした際に一律で 401 が返るか
     */
    public function test_unauthenticated_request_returns_401()
    {
        $genre = Genre::factory()->create();
        $book = Book::factory()->create();

        // 1. 新規登録（未認証）
        $this->postJson('/api/v1/books', [
            'title' => '未認証書籍',
            'author' => '未認証著者',
            'genres' => [$genre->id],
        ])->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']); // 👈 ハンドラーで実装したJSONのメッセージと一致

        // 2. 書籍更新（未認証）
        $this->putJson("/api/v1/books/{$book->id}", [
            'title' => '未認証更新',
            'author' => '未認証更新著者',
            'genres' => [$genre->id],
        ])->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);

        // 3. 書籍削除（未認証）
        $this->deleteJson("/api/v1/books/{$book->id}")
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    /**
     * ログインしていても、他人の本に対して更新・削除を実行しようとした際に 403 が返るか
     */
    public function test_other_users_book_operation_returns_403()
    {
        // 所有者、他ユーザー、ジャンルの用意
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $genre = Genre::factory()->create();

        // 所有者に紐づく書籍を作成
        $book = Book::factory()->create(['user_id' => $owner->id]);

        // 他ユーザーとしてログイン
        Sanctum::actingAs($otherUser);

        // 1. 他人の本を更新しようとした場合 -> 403
        $responseUpdate = $this->putJson("/api/v1/books/{$book->id}", [
            'title' => '乗っ取りタイトル',
            'author' => '乗っ取り著者',
            'genres' => [$genre->id],
        ]);
        $responseUpdate->assertStatus(403)
            ->assertJson(['message' => 'This action is unauthorized.']); // 👈 ハンドラーメッセージと一致

        // 2. 他人の本を削除しようとした場合 -> 403
        $responseDelete = $this->deleteJson("/api/v1/books/{$book->id}");
        $responseDelete->assertStatus(403)
            ->assertJson(['message' => 'This action is unauthorized.']);
    }
}
