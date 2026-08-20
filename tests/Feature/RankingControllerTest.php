<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingControllerTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // 1. アクセス認可のテスト
    // =========================================================================

    /**
     * 未ログイン（ゲスト）ユーザーおよびログインユーザーのどちらもランキング画面を表示できること
     */
    public function test_anyone_can_view_ranking_index(): void
    {
        // ゲストでのアクセス検証
        $response = $this->get(route('ranking.index'));
        $response->assertStatus(200);

        // ログインユーザーでのアクセス検証
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('ranking.index'));
        $response->assertStatus(200);
    }

    // =========================================================================
    // 2. フィルタリングと件数制限のテスト
    // =========================================================================

    /**
     * レビューが1件もない書籍はランキングに表示されないこと
     */
    public function test_books_without_reviews_are_excluded_from_ranking(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();

        // レビューのある本
        $reviewedBook = Book::factory()->create(['user_id' => $user->id]);
        Review::factory()->create([
            'book_id' => $reviewedBook->id,
            'user_id' => $user->id,
            'rating' => 4,
        ]);

        // レビューのない本
        $noReviewBook = Book::factory()->create(['user_id' => $user->id]);

        // Act (実行)
        $response = $this->get(route('ranking.index'));

        // Assert (検証)
        $response->assertStatus(200);

        // レビューのある本は表示され、ない本は表示されないこと
        $response->assertSee($reviewedBook->title);
        $response->assertDontSee($noReviewBook->title);
    }

    /**
     * ランキングに表示される書籍は最大で10件に制限されていること（TOP10）
     */
    public function test_ranking_shows_maximum_of_10_books(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();

        // 11件の書籍を作成し、すべてにレビューを1件ずつ紐付ける
        $books = Book::factory()->count(11)->create(['user_id' => $user->id]);
        foreach ($books as $book) {
            Review::factory()->create([
                'book_id' => $book->id,
                'user_id' => $user->id,
                'rating' => 3,
            ]);
        }

        // Act (実行)
        $response = $this->get(route('ranking.index'));

        // Assert (検証)
        $response->assertStatus(200);

        // 変数名を 'rankedBooks' に修正して存在確認
        $response->assertViewHas('rankedBooks');

        // 表示件数が最大「10件」であることを厳密に検証
        $this->assertCount(10, $response->viewData('rankedBooks'));
    }

    // =========================================================================
    // 3. ソート順（ビジネスルール）の厳密テスト
    // =========================================================================

    /**
     * ランキングが「平均評価の降順」「レビュー件数の降順」「IDの降順」の優先度で正しく並ぶこと
     */
    public function test_ranking_sorting_logic(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();

        // 【期待される1位】Book A: 平均評価 5.0 (レビュー1件), ID: 1
        $bookA = Book::factory()->create(['id' => 1, 'user_id' => $user->id, 'title' => 'Book A']);
        Review::factory()->create(['book_id' => $bookA->id, 'user_id' => $user->id, 'rating' => 5]);

        // 【期待される2位】Book B: 平均評価 4.0 (レビュー2件: 4, 4), ID: 2
        // ※Book C, Dと同点だが、レビュー件数（2件）が多いため上位にくる
        $bookB = Book::factory()->create(['id' => 2, 'user_id' => $user->id, 'title' => 'Book B']);
        Review::factory()->create(['book_id' => $bookB->id, 'user_id' => $user->id, 'rating' => 4]);
        Review::factory()->create(['book_id' => $bookB->id, 'user_id' => $user->id, 'rating' => 4]);

        // 【期待される3位】Book D: 平均評価 4.0 (レビュー1件), ID: 4
        // ※Book Cと同点・同レビュー数だが、IDが大きい（最新）ため上位にくる
        $bookD = Book::factory()->create(['id' => 4, 'user_id' => $user->id, 'title' => 'Book D']);
        Review::factory()->create(['book_id' => $bookD->id, 'user_id' => $user->id, 'rating' => 4]);

        // 【期待される4位】Book C: 平均評価 4.0 (レビュー1件), ID: 3
        $bookC = Book::factory()->create(['id' => 3, 'user_id' => $user->id, 'title' => 'Book C']);
        Review::factory()->create(['book_id' => $bookC->id, 'user_id' => $user->id, 'rating' => 4]);

        // 【期待される5位】Book E: 平均評価 3.0 (レビュー1件), ID: 5
        $bookE = Book::factory()->create(['id' => 5, 'user_id' => $user->id, 'title' => 'Book E']);
        Review::factory()->create(['book_id' => $bookE->id, 'user_id' => $user->id, 'rating' => 3]);

        // Act (実行)
        $response = $this->get(route('ranking.index'));

        // Assert (検証)
        $response->assertStatus(200);

        // 変数名を 'rankedBooks' に修正してビューデータを取得
        $viewBooks = $response->viewData('rankedBooks');

        // 件数と並び順を1件ずつ厳密にアサーション (配列インデックスで安全に取り出し)
        $this->assertCount(5, $viewBooks);
        $this->assertEquals($bookA->id, $viewBooks[0]->id, '1位は平均5.0のBook Aであるべき');
        $this->assertEquals($bookB->id, $viewBooks[1]->id, '2位は平均4.0・レビュー数最多のBook Bであるべき');
        $this->assertEquals($bookD->id, $viewBooks[2]->id, '3位は平均4.0・ID最大（最新）のBook Dであるべき');
        $this->assertEquals($bookC->id, $viewBooks[3]->id, '4位は平均4.0・ID最小（最古）のBook Cであるべき');
        $this->assertEquals($bookE->id, $viewBooks[4]->id, '5位は平均3.0의 Book Eであるべき');
    }
}
