<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Class ApiBookControllerTest
 *
 * 公開API（AP01〜AP06）における認証・認可、および各種CRUD処理とフィルタリング・バリデーションを検証するテストクラス。
 * 応用機能の開発プロセス要件に適合するため、全メソッドへの型宣言およびPHPDocを適用しています。
 */
class ApiBookControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * =========================================================================
     * GET（読み取り系）：認証不要のエンドポイントテスト
     * =========================================================================
     */

    /**
     * 正しいJSON構造で書籍一覧が取得できることを検証します。
     */
    public function test_can_get_book_list_with_correct_structure(): void
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

    /**
     * キーワードとジャンルIDを指定して、正しく書籍一覧をフィルタリングできるかを検証します。
     */
    public function test_can_filter_book_list_by_keyword_and_genre(): void
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

    /**
     * 正しくページネーションされた書籍一覧（1ページあたり指定件数）が取得できるかを検証します。
     */
    public function test_can_get_paginated_book_list(): void
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

    /**
     * 無効な検索パラメータを送信した際に、422バリデーションエラーが返却されるかを検証します。
     */
    public function test_get_book_list_validation_error(): void
    {
        $response = $this->getJson('/api/v1/books?genre=invalid&per_page=101');

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['genre', 'per_page']);
    }

    /**
     * 正しいJSON構造で指定された書籍の詳細情報が取得できるかを検証します。
     */
    public function test_can_get_book_detail_with_correct_structure(): void
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

    /**
     * 存在しない書籍IDを指定して詳細取得を試みた場合、404 Not Found が返却されるかを検証します。
     */
    public function test_get_book_detail_not_found(): void
    {
        $response = $this->getJson('/api/v1/books/99999');

        $response->assertStatus(404)
            ->assertJson([
                'message' => '指定された書籍が見つかりません。',
            ]);
    }

    /**
     * =========================================================================
     * POST/PUT/DELETE（書き込み系）：要Sanctum認証テスト
     * =========================================================================
     */

    /**
     * 認証済みのユーザーが、新規書籍を正しく登録できること（作成者IDが自動設定されること）を検証します。
     */
    public function test_can_store_book(): void
    {
        // 1. テストユーザーを作成
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        // 2. Sanctum疑似ログインを有効化
        Sanctum::actingAs($user);

        // 3. user_id パラメータは送信しない（コントローラーが自動セットする実仕様を検証）
        $response = $this->postJson('/api/v1/books', [
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9784101010014',
            'published_date' => '2023-01-01',
            'description' => 'テスト説明',
            'image_url' => 'https://example.com/image.jpg',
            'genres' => [$genre->id],
        ]);

        // 4. 201 Created レスポンスと、作成者IDにログイン中のユーザーIDがセットされていることを検証
        $response->assertStatus(201);
        $this->assertDatabaseHas('books', [
            'title' => 'テスト書籍',
            'user_id' => $user->id,
        ]);
    }

    /**
     * 必須項目が欠落している場合に、422バリデーションエラーとなるかを検証します。
     */
    public function test_store_book_validation_error_missing_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/books', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'author', 'genres']);
    }

    /**
     * 存在しないジャンルIDを指定して書籍登録を試みた場合、バリデーションエラーとなるかを検証します。
     */
    public function test_store_book_validation_error_master_existence(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/books', [
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'genres' => [99999], // 存在しないジャンルID
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['genres.0']);
    }

    /**
     * 重複するISBNを送信して書籍を登録しようとした場合、バリデーションエラーとなるかを検証します。
     */
    public function test_store_book_validation_error_duplicate_isbn(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Book::factory()->create(['isbn' => '9784101010014']);

        $response = $this->postJson('/api/v1/books', [
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9784101010014',
            'genres' => [Genre::factory()->create()->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['isbn']);
    }

    /**
     * 書籍の登録者が、自身の所有する書籍情報を正常に更新できるかを検証します。
     */
    public function test_can_update_book(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

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

    /**
     * 書籍更新時に、他書籍と重複するISBNを指定した場合にバリデーションエラーとなるかを検証します。
     */
    public function test_update_book_validation_error_duplicate_isbn(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $genre = Genre::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id, 'isbn' => '9784101010014']);
        Book::factory()->create(['isbn' => '9784101010021']); // 他書籍に登録済みのISBN

        $response = $this->putJson("/api/v1/books/{$book->id}", [
            'title' => '更新タイトル',
            'author' => '更新著者',
            'isbn' => '9784101010021', // 重複エラー対象
            'genres' => [$genre->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['isbn']);
    }

    /**
     * 存在しない書籍IDを指定して更新を試みた場合、404 Not Found が返却されるかを検証します。
     */
    public function test_update_book_not_found(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
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

    /**
     * 書籍の登録者が、自身の所有する書籍情報を正常に物理削除できるかを検証します。
     */
    public function test_can_delete_book(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $book = Book::factory()->create(['user_id' => $owner->id]);

        $response = $this->deleteJson("/api/v1/books/{$book->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => '書籍を削除しました。',
            ]);

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    /**
     * 存在しない書籍IDを指定して削除を試みた場合、404 Not Found が返却されるかを検証します。
     */
    public function test_delete_book_not_found(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/v1/books/99999');

        $response->assertStatus(404)
            ->assertJson([
                'message' => '指定された書籍が見つかりません。',
            ]);
    }

    /**
     * =========================================================================
     * 【新規追加】応用フェーズ用の異常系テスト（未認証 / 未認可）
     * =========================================================================
     */

    /**
     * 未認証（ゲスト）が書き込み系エンドポイント（POST/PUT/DELETE）にアクセスした際に、Sanctumにより一律で 401 Unauthorized が返却されることを検証します。
     */
    public function test_unauthenticated_request_returns_401(): void
    {
        $genre = Genre::factory()->create();
        $book = Book::factory()->create();

        // 1. 新規登録（未認証） -> 401
        $this->postJson('/api/v1/books', [
            'title' => '未認証書籍',
            'author' => '未認証著者',
            'genres' => [$genre->id],
        ])->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);

        // 2. 書籍更新（未認証） -> 401
        $this->putJson("/api/v1/books/{$book->id}", [
            'title' => '未認証更新',
            'author' => '未認証更新著者',
            'genres' => [$genre->id],
        ])->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);

        // 3. 書籍削除（未認証） -> 401
        $this->deleteJson("/api/v1/books/{$book->id}")
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    /**
     * 認証済みユーザーであっても、他人の所有する書籍に対して更新・削除を実行しようとした際に、BookPolicyにより 403 Forbidden が返却されることを検証します。
     */
    public function test_other_users_book_operation_returns_403(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $genre = Genre::factory()->create();

        $book = Book::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($otherUser);

        // 1. 他人の書籍を更新しようとした場合 -> 403
        $responseUpdate = $this->putJson("/api/v1/books/{$book->id}", [
            'title' => '乗っ取りタイトル',
            'author' => '乗っ取り著者',
            'genres' => [$genre->id],
        ]);
        $responseUpdate->assertStatus(403)
            ->assertJson(['message' => 'This action is unauthorized.']);

        // 2. 他人の書籍を削除しようとした場合 -> 403
        $responseDelete = $this->deleteJson("/api/v1/books/{$book->id}");
        $responseDelete->assertStatus(403)
            ->assertJson(['message' => 'This action is unauthorized.']);
    }
}
