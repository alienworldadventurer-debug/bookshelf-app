<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiBookControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 正しいJSON構造で書籍一覧が取得できることを検証します。
     *
     * @return void 書籍一覧のレスポンス構造が期待どおりであることを確認する
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
     *
     * @return void 条件に一致する書籍のみが返ることを確認する
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
     *
     * @return void 指定件数とページネーションメタデータが返ることを確認する
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
     * パラメータ未指定時に、デフォルトの「20件」でページネーションされるかを検証します。
     *
     * @return void 既定の1ページ件数が20件であることを確認する
     */
    public function test_can_get_paginated_book_list_with_default_per_page(): void
    {
        $genre = Genre::factory()->create();
        Book::factory()->count(21)->hasAttached($genre)->create();

        $response = $this->getJson('/api/v1/books');

        $response->assertStatus(200)
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.per_page', 20);
    }

    /**
     * 無効な検索パラメータを送信した際に、422バリデーションエラーが返却されるかを検証します。
     *
     * @return void 無効な検索条件に対するエラー形式を確認する
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
     *
     * @return void 書籍詳細と関連レビューのレスポンス構造を確認する
     */
    public function test_can_get_book_detail_with_correct_structure(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->hasAttached($genre)->create();

        Review::factory()->create([
            'book_id' => $book->id,
            'user_id' => $user->id,
            'rating' => 5,
            'comment' => '素晴らしい本でした！',
        ]);

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
    }

    /**
     * 存在しない書籍IDを指定して詳細取得を試みた場合、404 Not Found が返却されるかを検証します。
     *
     * @return void 存在しない書籍に対して404が返ることを確認する
     */
    public function test_get_book_detail_not_found(): void
    {
        $response = $this->getJson('/api/v1/books/99999');

        $response->assertStatus(404)
            ->assertJson([
                'error' => '指定された書籍が見つかりません。',
            ]);
    }

    /**
     * per_pageパラメータに100を超える値を指定した際、
     * 422エラーとなり、正しく最大値制限のバリデーションエラーが発生することを検証します。
     *
     * @return void per_pageの上限超過に対するエラー内容を確認する
     */
    public function test_get_book_list_validation_error_max_per_page(): void
    {
        $response = $this->getJson('/api/v1/books?per_page=101');

        $response->assertStatus(422)
            ->assertJson([
                'message' => '入力内容に不備があります。',
            ])
            ->assertJsonValidationErrors([
                'per_page' => '1ページあたりの件数は100以下の値を指定してください。',
            ]);
    }

    /**
     * 認証済みのユーザーが、新規書籍を正しく登録できること（作成者IDが自動設定されること）を検証します。
     *
     * @return void 書籍が作成され、認証ユーザーが登録者になることを確認する
     */
    public function test_can_store_book(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/books', [
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9784101010014',
            'published_date' => '2023-01-01',
            'description' => 'テスト説明',
            'image_url' => 'https://example.com/image.jpg',
            'genres' => [$genre->id],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('books', [
            'title' => 'テスト書籍',
            'user_id' => $user->id,
        ]);
    }

    /**
     * 必須項目が欠落している場合に、422バリデーションエラーとなるかを検証します。
     *
     * @return void 必須項目のバリデーションエラーを確認する
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
     *
     * @return void 存在しないジャンルIDが拒否されることを確認する
     */
    public function test_store_book_validation_error_master_existence(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/books', [
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'genres' => [99999],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['genres.0']);
    }

    /**
     * 重複するISBNを送信して書籍を登録しようとした場合、バリデーションエラーとなるかを検証します。
     *
     * @return void ISBN重複時のバリデーションエラーを確認する
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
     *
     * @return void 所有者による書籍更新が成功することを確認する
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
     *
     * @return void ISBN重複時の更新拒否を確認する
     */
    public function test_update_book_validation_error_duplicate_isbn(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $genre = Genre::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id, 'isbn' => '9784101010014']);
        Book::factory()->create(['isbn' => '9784101010021']);

        $response = $this->putJson("/api/v1/books/{$book->id}", [
            'title' => '更新タイトル',
            'author' => '更新著者',
            'isbn' => '9784101010021',
            'genres' => [$genre->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['isbn']);
    }

    /**
     * 存在しない書籍IDを指定して更新を試みた場合、404 Not Found が返却されるかを検証します。
     *
     * @return void 存在しない書籍の更新に404が返ることを確認する
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
                'error' => '指定された書籍が見つかりません。',
            ]);
    }

    /**
     * 書籍の登録者が、自身の所有する書籍情報を正常に物理削除できるかを検証します。
     *
     * @return void 所有者による書籍削除とデータ消失を確認する
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
     *
     * @return void 存在しない書籍の削除に404が返ることを確認する
     */
    public function test_delete_book_not_found(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/v1/books/99999');

        $response->assertStatus(404)
            ->assertJson([
                'error' => '指定された書籍が見つかりません。',
            ]);
    }

    /**
     * 未認証（ゲスト）が書き込み系エンドポイント（POST/PUT/DELETE）にアクセスした際に、Sanctumにより一律で 401 Unauthorized が返却されることを検証します。
     *
     * @return void 未認証の書き込み操作が401で拒否されることを確認する
     */
    public function test_unauthenticated_request_returns_401(): void
    {
        $genre = Genre::factory()->create();
        $book = Book::factory()->create();

        $this->postJson('/api/v1/books', [
            'title' => '未認証書籍',
            'author' => '未認証著者',
            'genres' => [$genre->id],
        ])->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);

        $this->putJson("/api/v1/books/{$book->id}", [
            'title' => '未認証更新',
            'author' => '未認証更新著者',
            'genres' => [$genre->id],
        ])->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);

        $this->deleteJson("/api/v1/books/{$book->id}")
            ->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    /**
     * 認証済みユーザーであっても、他人の所有する書籍に対して更新・削除を実行しようとした際に、BookPolicyにより 403 Forbidden が返却されることを検証します。
     *
     * @return void 所有者以外の更新・削除が403で拒否されることを確認する
     */
    public function test_other_users_book_operation_returns_403(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $genre = Genre::factory()->create();

        $book = Book::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($otherUser);

        $responseUpdate = $this->putJson("/api/v1/books/{$book->id}", [
            'title' => '乗っ取りタイトル',
            'author' => '乗っ取り著者',
            'genres' => [$genre->id],
        ]);
        $responseUpdate->assertStatus(403)
            ->assertJson(['error' => 'この操作を実行する権限がありません。']);

        $responseDelete = $this->deleteJson("/api/v1/books/{$book->id}");
        $responseDelete->assertStatus(403)
            ->assertJson(['error' => 'この操作を実行する権限がありません。']);
    }
}
