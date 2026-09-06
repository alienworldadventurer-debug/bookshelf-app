<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\ReadingPlan;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyReportControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * ログイン中のユーザーの読書レポート統計が正しく集計されて表示されること
     */
    public function test_マイ読書レポートの統計データが正しく集計されて表示されること(): void
    {
        // --------------------------------------------------
        // Arrange (準備)
        // --------------------------------------------------
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // テスト用のジャンルを作成
        $genreNovel = Genre::create(['name' => '小説']);
        $genreTech = Genre::create(['name' => '技術書']);

        // 1. レビューと読了数 (User自身)
        // 本A: 小説 / 評価 5 / 読了(completed)
        $bookA = Book::factory()->create(['user_id' => $user->id]);
        $bookA->genres()->attach($genreNovel->id);
        Review::factory()->create(['book_id' => $bookA->id, 'user_id' => $user->id, 'rating' => 5]);
        ReadingPlan::factory()->create(['book_id' => $bookA->id, 'user_id' => $user->id, 'status' => 'completed']);

        // 本B: 技術書 / 評価 4 / 読了(completed)
        $bookB = Book::factory()->create(['user_id' => $user->id]);
        $bookB->genres()->attach($genreTech->id);
        Review::factory()->create(['book_id' => $bookB->id, 'user_id' => $user->id, 'rating' => 4]);
        ReadingPlan::factory()->create(['book_id' => $bookB->id, 'user_id' => $user->id, 'status' => 'completed']);

        // 本C: 小説 / 評価 2 / 進行中(in_progress) -> 読了していない
        $bookC = Book::factory()->create(['user_id' => $user->id]);
        $bookC->genres()->attach($genreNovel->id);
        Review::factory()->create(['book_id' => $bookC->id, 'user_id' => $user->id, 'rating' => 2]);
        ReadingPlan::factory()->create(['book_id' => $bookC->id, 'user_id' => $user->id, 'status' => 'in_progress']);

        // 2. 他のユーザーのデータ（ノイズ。集計に混ざってはいけない）
        $otherBook = Book::factory()->create(['user_id' => $otherUser->id]);
        Review::factory()->create(['book_id' => $otherBook->id, 'user_id' => $otherUser->id, 'rating' => 5]);
        ReadingPlan::factory()->create(['book_id' => $otherBook->id, 'user_id' => $otherUser->id, 'status' => 'completed']);

        // --------------------------------------------------
        // Act (実行)
        // --------------------------------------------------
        $response = $this->actingAs($user)->get(route('reports.index'));

        // --------------------------------------------------
        // Assert (検証)
        // --------------------------------------------------
        $response->assertStatus(200);

        // Bladeに渡された集計データ（変数）を検証
        $response->assertViewHas('stats');
        $stats = $response->original->getData()['stats'];

        // ① 総レビュー数 (本A, B, C の計3件。他人のデータは含まない)
        $this->assertEquals(3, $stats['summary']['total_reviews']);

        // ② 読了冊数 (statusがcompletedなのは 本A, B の計2冊)
        $this->assertEquals(2, $stats['summary']['books_read']);

        // ③ 平均評価 ( (5 + 4 + 2) / 3 = 3.666... -> 3.7 )
        $this->assertEquals(3.7, round($stats['summary']['average_rating'], 1));

        // ④ 星評価分布 (星1[0]: 0, 星2[1]: 1, 星3[2]: 0, 星4[3]: 1, 星5[4]: 1)
        $ratingDistribution = $stats['rating_distribution'];
        $this->assertEquals(0, $ratingDistribution[0]); // // 星1
        $this->assertEquals(1, $ratingDistribution[1]); // // 星2
        $this->assertEquals(0, $ratingDistribution[2]); // // 星3
        $this->assertEquals(1, $ratingDistribution[3]); // // 星4
        $this->assertEquals(1, $ratingDistribution[4]); // // 星5

        // ⑤ 高評価書籍 TOP5 (4星以上が対象のため、本A(5)と本B(4)のみ)
        $topBooks = $stats['top_rated_books'];
        $this->assertCount(2, $topBooks);
        $this->assertEquals($bookA->id, $topBooks[0]['id']);
        $this->assertEquals($bookB->id, $topBooks[1]['id']);

        // ⑥ ジャンル別評価傾向 (技術書: 4.0が1位、小説: 3.5が2位)
        $genreRatings = $stats['genre_ratings'];
        $this->assertCount(2, $genreRatings);

        $this->assertEquals('技術書', $genreRatings[0]['name']);
        $this->assertEquals(4.0, $genreRatings[0]['average_rating']);

        $this->assertEquals('小説', $genreRatings[1]['name']);
        $this->assertEquals(3.5, $genreRatings[1]['average_rating']);
    }
}
