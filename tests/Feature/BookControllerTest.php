<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\ReadingPlan;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
     * キーワード検索で条件に部分一致する書籍のみが表示されること
     */
    public function test_index_filters_books_by_keyword(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        Book::create([
            'user_id' => $user->id,
            'title' => '吾輩は猫である',
            'author' => '夏目漱石',
        ]);
        Book::create([
            'user_id' => $user->id,
            'title' => 'リーダブルコード',
            'author' => 'Dustin Boswell',
        ]);

        // Act (実行)
        $response = $this->get(route('books.index', ['keyword' => '猫']));

        // Assert (検証)
        $response->assertStatus(200);
        $response->assertSee('吾輩は猫である');
        $response->assertDontSee('リーダブルコード');
    }

    /**
     * ジャンル選択で指定したジャンルが紐付く書籍のみが絞り込まれること
     */
    public function test_index_filters_books_by_genre(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $genreNovel = Genre::create(['name' => '小説']);
        $genreTech = Genre::create(['name' => '技術書']);

        $book1 = Book::create([
            'user_id' => $user->id,
            'title' => '吾輩は猫である',
            'author' => '夏目漱石',
        ]);
        $book1->genres()->attach($genreNovel->id);

        $book2 = Book::create([
            'user_id' => $user->id,
            'title' => 'リーダブルコード',
            'author' => 'Dustin Boswell',
        ]);
        $book2->genres()->attach($genreTech->id);

        // Act (実行)
        $response = $this->get(route('books.index', ['genre' => $genreTech->id]));

        // Assert (検証)
        $response->assertStatus(200);
        $response->assertSee('リーダブルコード');
        $response->assertDontSee('吾輩は猫である');
    }

    /**
     * 指定したソート順（最新順・古い順・タイトル順・評価順）で書籍が正しく並び替わること
     */
    public function test_index_sorts_books_correctly(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();

        // 登録日順、タイトル順が明確に異なる3冊を作成（作成時間に差をつける）
        $book1 = Book::factory()->create([
            'user_id' => $user->id,
            'title' => 'A_Book_吾輩は猫である',
            'author' => '夏目漱石',
            'created_at' => now()->subDays(3),
        ]);

        $book2 = Book::factory()->create([
            'user_id' => $user->id,
            'title' => 'C_Book_リーダブルコード',
            'author' => 'Dustin Boswell',
            'created_at' => now(),
        ]);

        $book3 = Book::factory()->create([
            'user_id' => $user->id,
            'title' => 'B_Book_坊っちゃん',
            'author' => '夏目漱石',
            'created_at' => now()->subDays(1),
        ]);

        // 評価の設定 (book1: 5.0, book3: 2.0, book2: レビューなし)
        Review::create([
            'user_id' => $user->id,
            'book_id' => $book1->id,
            'rating' => 5,
            'comment' => '素晴らしい本でした！',
        ]);
        Review::create([
            'user_id' => $user->id,
            'book_id' => $book3->id,
            'rating' => 2,
            'comment' => '内容が薄い印象。',
        ]);

        // --------------------------------------------------
        // 1. 最新順 (newest: book2 -> book3 -> book1)
        // --------------------------------------------------
        $responseNewest = $this->get(route('books.index', ['sort' => 'newest']));
        $booksNewest = $responseNewest->original->getData()['books'];
        $this->assertEquals($book2->id, $booksNewest[0]->id);
        $this->assertEquals($book3->id, $booksNewest[1]->id);
        $this->assertEquals($book1->id, $booksNewest[2]->id);

        // --------------------------------------------------
        // 2. 古い順 (oldest: book1 -> book3 -> book2)
        // --------------------------------------------------
        $responseOldest = $this->get(route('books.index', ['sort' => 'oldest']));
        $booksOldest = $responseOldest->original->getData()['books'];
        $this->assertEquals($book1->id, $booksOldest[0]->id);
        $this->assertEquals($book3->id, $booksOldest[1]->id);
        $this->assertEquals($book2->id, $booksOldest[2]->id);

        // --------------------------------------------------
        // 3. タイトル順 (title: book1(A) -> book3(B) -> book2(C))
        // --------------------------------------------------
        $responseTitle = $this->get(route('books.index', ['sort' => 'title']));
        $booksTitle = $responseTitle->original->getData()['books'];
        $this->assertEquals($book1->id, $booksTitle[0]->id);
        $this->assertEquals($book3->id, $booksTitle[1]->id);
        $this->assertEquals($book2->id, $booksTitle[2]->id);

        // --------------------------------------------------
        // 4. 評価順 (rating: book1(5点) -> book3(2点) -> book2(レビューなし) 👈 ここが最重要ポイント！)
        // --------------------------------------------------
        $responseRating = $this->get(route('books.index', ['sort' => 'rating']));
        $booksRating = $responseRating->original->getData()['books'];
        $this->assertEquals($book1->id, $booksRating[0]->id); // 高評価
        $this->assertEquals($book3->id, $booksRating[1]->id); // 低評価
        $this->assertEquals($book2->id, $booksRating[2]->id); // レビューなしが一番最後
    }

    /**
     * 検索・絞り込み・ソート条件がページネーションリンクに正しく引き継がれること
     */
    public function test_index_pagination_retains_query_parameters(): void
    {
        // Arrange (準備): ジャンルを1つ用意
        $user = User::factory()->create();
        $genre = Genre::create(['name' => '小説']);

        // 1ページ最大10件のため、条件に合う書籍を11件作って2ページ目が発生するようにする
        Book::factory()->count(11)->create(['user_id' => $user->id])->each(function ($book) use ($genre) {
            $book->genres()->attach($genre->id);
            $book->update(['title' => 'Laravel_Book_'.$book->id]);
        });

        // Act (実行): 検索、絞り込み、ソートを組み合わせて一覧をリクエスト
        $response = $this->get(route('books.index', [
            'keyword' => 'Laravel',
            'genre' => $genre->id,
            'sort' => 'title',
        ]));

        // Assert (検証)
        $response->assertStatus(200);
        $books = $response->original->getData()['books'];

        // 2ページ目へのリンクに、指定したクエリパラメータがすべて引き継がれているかを検証
        $nextPageUrl = $books->nextPageUrl();
        $this->assertStringContainsString('keyword=Laravel', $nextPageUrl);
        $this->assertStringContainsString('genre='.$genre->id, $nextPageUrl);
        $this->assertStringContainsString('sort=title', $nextPageUrl);
    }

    /**
     * ログイン・未ログインに応じて書籍詳細画面 of 表示やアクションボタンの状態が切り替わること
     */
    public function test_show_displays_book_details_and_button_visibility(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $genreTech = Genre::create(['name' => '技術書']);
        $book = Book::create([
            'user_id' => $user->id,
            'title' => 'テスト技術書',
            'author' => 'テスト著者',
            'isbn' => null,
            'published_date' => null,
            'description' => null,
        ]);
        $book->genres()->attach($genreTech->id);

        // --- 1. 未ログインの場合 ---
        // Act
        $guestResponse = $this->get(route('books.show', $book));
        // Assert
        $guestResponse->assertStatus(200);
        $guestResponse->assertSee('テスト技術書');
        $guestResponse->assertSee('未登録'); // nullフィールドの代替表示
        $guestResponse->assertDontSee('お気に入りから削除');
        $guestResponse->assertSee('ログイン');

        // --- 2. ログイン済みの場合 ---
        // Act
        $authResponse = $this->actingAs($user)->get(route('books.show', $book));
        // Assert
        $authResponse->assertStatus(200);
        $authResponse->assertSee('お気に入りに追加');
        $authResponse->assertSee('投稿する'); // レビュー投稿ボタン
    }

    // =========================================================================
    // 2. ISBN検索（非同期API）のテスト (正常系)
    // =========================================================================

    /**
     * 正しいISBNで検索した際、Google Books APIのモックを介して整形された書籍情報JSONが返却されること
     */
    public function test_isbn_search_returns_json_successfully_using_mock(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $isbn = '9784798157573';

        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([
                'totalItems' => 1,
                'items' => [
                    [
                        'volumeInfo' => [
                            'title' => 'JavaScript逆引きレシピ 第2版',
                            'authors' => ['山田祥寛'],
                            'description' => 'JS of reverse dictionary',
                            'publishedDate' => '2018-10-18',
                            'imageLinks' => [
                                'thumbnail' => 'http://books.google.com/test-image.jpg',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Act (実行)
        $response = $this->actingAs($user)->get("/books/isbn/{$isbn}");

        // Assert (検証)
        $response->assertStatus(200);
        $response->assertJson([
            'title' => 'JavaScript逆引きレシピ 第2版',
            'author' => '山田祥寛',
            'description' => 'JS of reverse dictionary',
            'published_date' => '2018-10-18',
            'image_url' => 'https://books.google.com/test-image.jpg',
        ]);
    }

    // =========================================================================
    // 3. ISBN検索（非同期API）のテスト (異常系)
    // =========================================================================

    /**
     * Google Books APIで書籍が見つからない場合、適切なエラーメッセージと404を返却すること
     */
    public function test_isbn_search_returns_404_when_book_not_found(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $isbn = '9789999999999';

        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([
                'totalItems' => 0,
            ], 200),
        ]);

        // Act (実行)
        $response = $this->actingAs($user)->get("/books/isbn/{$isbn}");

        // Assert (検証)
        $response->assertStatus(404);
        $response->assertJson([
            'error' => '書籍情報が見つかりませんでした。',
        ]);
    }

    /**
     * 入力されたISBNの桁数や形式が不正な場合、422バリデーションエラーを返却すること
     */
    public function test_isbn_search_returns_422_when_isbn_format_invalid(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $invalidIsbn = 'abc1234567890';

        // Act (実行)
        $response = $this->actingAs($user)->get("/books/isbn/{$invalidIsbn}");

        // Assert (検証)
        $response->assertStatus(422);
        $response->assertJson([
            'error' => 'ISBNは13桁の半角数字で入力してください。',
        ]);
    }

    /**
     * 外部APIの接続障害が発生した場合、復旧を促すメッセージと500エラーを返却すること
     */
    public function test_isbn_search_returns_500_on_api_connection_error(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $isbn = '9784798157573';

        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([], 503),
        ]);

        // Act (実行)
        $response = $this->actingAs($user)->get("/books/isbn/{$isbn}");

        // Assert (検証)
        $response->assertStatus(500);
        $response->assertJson([
            'error' => '書籍情報の取得に失敗しました。時間をおいて再度お試しいただくか、手動で入力してください。',
        ]);
    }

    // =========================================================================
    // 4. 書籍登録・更新・削除のテスト (正常系)
    // =========================================================================

    /**
     * 必須情報のみを入力した際、ISBNや出版日が空でも正常に新規登録ができること
     */
    public function test_store_creates_book_with_nullable_fields(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $genreNovel = Genre::create(['name' => '小説']);

        // Act (実行)
        $response = $this->actingAs($user)->post(route('books.store'), [
            'title' => '吾輩は猫である',
            'author' => '夏目漱石',
            'isbn' => null,
            'published_date' => null,
            'description' => 'テスト用説明',
            'genres' => [$genreNovel->id],
        ]);

        // Assert (検証)
        $response->assertRedirect(route('books.index'));
        $response->assertSessionHas('success', '書籍を登録しました。');

        $this->assertDatabaseHas('books', [
            'title' => '吾輩は猫である',
            'isbn' => null,
            'published_date' => null,
        ]);
    }

    /**
     * 登録者本人が自身の書籍を編集画面から安全に更新できること（自身のISBNはユニーク対象外）
     */
    public function test_update_modifies_book_successfully(): void
    {
        // Arrange (準備)
        $owner = User::factory()->create();
        $genreNovel = Genre::create(['name' => '小説']);

        $myBook = Book::create([
            'user_id' => $owner->id,
            'title' => '私の本',
            'author' => '著者A',
            'isbn' => '9784101010014',
        ]);
        $myBook->genres()->attach($genreNovel->id);

        // Act (実行)
        $response = $this->actingAs($owner)->put(route('books.update', $myBook), [
            'title' => '私の本（改訂版）',
            'author' => '著者A',
            'isbn' => '9784101010014', // 変更なし
            'genres' => [$genreNovel->id],
        ]);

        // Assert (検証)
        $response->assertRedirect(route('books.show', $myBook));
        $response->assertSessionHas('success', '書籍情報を更新しました。');
        $this->assertDatabaseHas('books', [
            'id' => $myBook->id,
            'title' => '私の本（改訂版）',
        ]);
    }

    /**
     * 登録者本人が書籍を削除した際、お気に入りやレビューなどの関連データもすべて連動して物理削除されること
     */
    public function test_destroy_deletes_book_and_cascades_associated_data(): void
    {
        // Arrange (準備)
        $owner = User::factory()->create();
        $genreNovel = Genre::create(['name' => '小説']);

        $book = Book::create([
            'user_id' => $owner->id,
            'title' => '削除される本',
            'author' => '著者',
        ]);
        $book->genres()->attach($genreNovel->id);

        $review = Review::create([
            'user_id' => $owner->id,
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => '素晴らしい本でした！',
        ]);

        $readingPlan = ReadingPlan::create([
            'user_id' => $owner->id,
            'book_id' => $book->id,
            'target_date' => now()->addDays(7),
            'status' => 'in_progress',
        ]);

        // Act (実行)
        $response = $this->actingAs($owner)->delete(route('books.destroy', $book));

        // Assert (検証)
        $response->assertRedirect(route('books.index'));
        $response->assertSessionHas('success', '書籍を削除しました。');

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id]);
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertDatabaseMissing('reading_plans', ['id' => $readingPlan->id]);
    }

    // =========================================================================
    // 5. 書籍登録・更新・削除のテスト (異常系)
    // =========================================================================

    /**
     * 必須項目が抜けている、またはISBNに他書籍との重複がある場合は登録が拒否されること
     */
    public function test_store_validation_fails_on_invalid_data(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $genreNovel = Genre::create(['name' => '小説']);

        Book::create([
            'user_id' => $user->id,
            'title' => '既存の書籍',
            'author' => '既存の著者',
            'isbn' => '9784101010014',
        ]);

        // 1. 必須項目欠落の検証
        // Act
        $responseMissing = $this->actingAs($user)->post(route('books.store'), [
            'title' => '',
            'author' => '',
            'genres' => [],
        ]);
        // Assert
        $responseMissing->assertSessionHasErrors(['title', 'author', 'genres']);

        // 2. ISBN重複の検証
        // Act
        $responseDuplicate = $this->actingAs($user)->post(route('books.store'), [
            'title' => '新規書籍',
            'author' => '新規著者',
            'isbn' => '9784101010014', // 重複
            'genres' => [$genreNovel->id],
        ]);
        // Assert
        $responseDuplicate->assertSessionHasErrors(['isbn']);
    }

    /**
     * 他人の書籍を編集・更新しようとした場合、認可（Policy）によって 403 Forbidden になること
     */
    public function test_edit_and_update_unauthorized_fails_on_other_user_book(): void
    {
        // Arrange (準備)
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $genreNovel = Genre::create(['name' => '小説']);

        $book = Book::create([
            'user_id' => $owner->id,
            'title' => '私の本',
            'author' => '著者',
        ]);

        // 1. 編集画面への他者アクセス
        // Act
        $responseEdit = $this->actingAs($otherUser)->get(route('books.edit', $book));
        // Assert
        $responseEdit->assertStatus(403);

        // 2. 更新処理への他者アクセス
        // Act
        $responseUpdate = $this->actingAs($otherUser)->put(route('books.update', $book), [
            'title' => '他人が不正に更新',
            'author' => '著者',
            'genres' => [$genreNovel->id],
        ]);
        // Assert
        $responseUpdate->assertStatus(403);
    }

    /**
     * 書籍更新時、自分以外の他の書籍で既に登録済みのISBNを指定すると重複エラーになること
     */
    public function test_update_validation_fails_on_duplicate_isbn(): void
    {
        // Arrange (準備)
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $genreNovel = Genre::create(['name' => '小説']);

        $myBook = Book::create([
            'user_id' => $owner->id,
            'title' => '私の本',
            'author' => '著者A',
            'isbn' => '9784101010014',
        ]);
        $myBook->genres()->attach($genreNovel->id);

        Book::create([
            'user_id' => $otherUser->id,
            'title' => '他人の本',
            'author' => '著者B',
            'isbn' => '9784422100524',
        ]);

        // Act (実行)
        $response = $this->actingAs($owner)->put(route('books.update', $myBook), [
            'title' => '私の本（改訂版）',
            'author' => '著者A',
            'isbn' => '9784422100524', // 他人が使っているISBNに変更しようとする
            'genres' => [$genreNovel->id],
        ]);

        // Assert (検証)
        $response->assertSessionHasErrors(['isbn']);
    }

    /**
     * 他人の書籍を削除しようとした場合、認可（Policy）により 403 Forbidden となり、データベースからも削除されないこと
     */
    public function test_destroy_unauthorized_fails_on_other_user_book(): void
    {
        // Arrange (準備)
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::create([
            'user_id' => $owner->id,
            'title' => '削除されない本',
            'author' => '著者',
        ]);

        // Act (実行)
        $response = $this->actingAs($otherUser)->delete(route('books.destroy', $book));

        // Assert (検証)
        $response->assertStatus(403);
        $this->assertDatabaseHas('books', ['id' => $book->id]);
    }
}
