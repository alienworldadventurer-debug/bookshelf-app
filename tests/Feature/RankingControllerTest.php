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

    /**
     * ゲストとログインユーザーの両方がランキング画面を表示できることを検証する。
     *
     * @return void ランキング画面へのアクセスが成功することを確認する
     */
    public function test_anyone_can_view_ranking_index(): void
    {
        $response = $this->get(route('ranking.index'));
        $response->assertStatus(200);

        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('ranking.index'));
        $response->assertStatus(200);
    }

    /**
     * レビューがない書籍がランキングから除外されることを検証する。
     *
     * @return void レビューの有無に応じた表示結果を確認する
     */
    public function test_books_without_reviews_are_excluded_from_ranking(): void
    {
        $user = User::factory()->create();

        $reviewedBook = Book::factory()->create(['user_id' => $user->id]);
        Review::factory()->create([
            'book_id' => $reviewedBook->id,
            'user_id' => $user->id,
            'rating' => 4,
        ]);

        $noReviewBook = Book::factory()->create(['user_id' => $user->id]);

        $response = $this->get(route('ranking.index'));

        $response->assertStatus(200);
        $response->assertSee($reviewedBook->title);
        $response->assertDontSee($noReviewBook->title);
    }

    /**
     * ランキングに表示される書籍が最大10件に制限されることを検証する。
     *
     * @return void ランキング一覧の表示件数を確認する
     */
    public function test_ranking_shows_maximum_of_10_books(): void
    {
        $user = User::factory()->create();

        $books = Book::factory()->count(11)->create(['user_id' => $user->id]);
        foreach ($books as $book) {
            Review::factory()->create([
                'book_id' => $book->id,
                'user_id' => $user->id,
                'rating' => 3,
            ]);
        }

        $response = $this->get(route('ranking.index'));

        $response->assertStatus(200);
        $response->assertViewHas('rankedBooks');
        $this->assertCount(10, $response->viewData('rankedBooks'));
    }

    /**
     * ランキングが平均評価、レビュー件数、書籍IDの降順で並ぶことを検証する。
     *
     * @return void 複数のソート条件に基づくランキング順を確認する
     */
    public function test_ranking_sorting_logic(): void
    {
        $user = User::factory()->create();

        $bookA = Book::factory()->create([
            'id' => 1,
            'user_id' => $user->id,
            'title' => 'Book A',
        ]);
        Review::factory()->create([
            'book_id' => $bookA->id,
            'user_id' => $user->id,
            'rating' => 5,
        ]);

        $bookB = Book::factory()->create([
            'id' => 2,
            'user_id' => $user->id,
            'title' => 'Book B',
        ]);
        Review::factory()->create([
            'book_id' => $bookB->id,
            'user_id' => $user->id,
            'rating' => 4,
        ]);
        Review::factory()->create([
            'book_id' => $bookB->id,
            'user_id' => $user->id,
            'rating' => 4,
        ]);

        $bookD = Book::factory()->create([
            'id' => 4,
            'user_id' => $user->id,
            'title' => 'Book D',
        ]);
        Review::factory()->create([
            'book_id' => $bookD->id,
            'user_id' => $user->id,
            'rating' => 4,
        ]);

        $bookC = Book::factory()->create([
            'id' => 3,
            'user_id' => $user->id,
            'title' => 'Book C',
        ]);
        Review::factory()->create([
            'book_id' => $bookC->id,
            'user_id' => $user->id,
            'rating' => 4,
        ]);

        $bookE = Book::factory()->create([
            'id' => 5,
            'user_id' => $user->id,
            'title' => 'Book E',
        ]);
        Review::factory()->create([
            'book_id' => $bookE->id,
            'user_id' => $user->id,
            'rating' => 3,
        ]);

        $response = $this->get(route('ranking.index'));

        $response->assertStatus(200);
        $viewBooks = $response->viewData('rankedBooks');

        $this->assertCount(5, $viewBooks);
        $this->assertEquals($bookA->id, $viewBooks[0]->id, '1位は平均5.0のBook Aであるべき');
        $this->assertEquals($bookB->id, $viewBooks[1]->id, '2位は平均4.0・レビュー数最多のBook Bであるべき');
        $this->assertEquals($bookD->id, $viewBooks[2]->id, '3位は平均4.0・ID最大（最新）のBook Dであるべき');
        $this->assertEquals($bookC->id, $viewBooks[3]->id, '4位は平均4.0・ID最小（最古）のBook Cであるべき');
        $this->assertEquals($bookE->id, $viewBooks[4]->id, '5位は平均3.0のBook Eであるべき');
    }
}
