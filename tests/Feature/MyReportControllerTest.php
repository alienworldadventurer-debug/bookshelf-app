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
     * ログインユーザーの読書レポートが自身のレビューと読書状況だけを集計して表示することを検証する。
     *
     * @return void レポートの概要、評価分布、上位書籍、ジャンル別評価を確認する
     */
    public function test_マイ読書レポートの統計データが正しく集計されて表示されること(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $genreNovel = Genre::create(['name' => '小説']);
        $genreTech = Genre::create(['name' => '技術書']);

        $bookA = Book::factory()->create(['user_id' => $user->id]);
        $bookA->genres()->attach($genreNovel->id);
        Review::factory()->create([
            'book_id' => $bookA->id,
            'user_id' => $user->id,
            'rating' => 5,
        ]);
        ReadingPlan::factory()->create([
            'book_id' => $bookA->id,
            'user_id' => $user->id,
            'status' => 'completed',
        ]);

        $bookB = Book::factory()->create(['user_id' => $user->id]);
        $bookB->genres()->attach($genreTech->id);
        Review::factory()->create([
            'book_id' => $bookB->id,
            'user_id' => $user->id,
            'rating' => 4,
        ]);
        ReadingPlan::factory()->create([
            'book_id' => $bookB->id,
            'user_id' => $user->id,
            'status' => 'completed',
        ]);

        $bookC = Book::factory()->create(['user_id' => $user->id]);
        $bookC->genres()->attach($genreNovel->id);
        Review::factory()->create([
            'book_id' => $bookC->id,
            'user_id' => $user->id,
            'rating' => 2,
        ]);
        ReadingPlan::factory()->create([
            'book_id' => $bookC->id,
            'user_id' => $user->id,
            'status' => 'in_progress',
        ]);

        $otherBook = Book::factory()->create(['user_id' => $otherUser->id]);
        Review::factory()->create([
            'book_id' => $otherBook->id,
            'user_id' => $otherUser->id,
            'rating' => 5,
        ]);
        ReadingPlan::factory()->create([
            'book_id' => $otherBook->id,
            'user_id' => $otherUser->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user)->get(route('reports.index'));

        $response->assertStatus(200);
        $response->assertViewHas('stats');
        $stats = $response->original->getData()['stats'];

        $this->assertEquals(3, $stats['summary']['total_reviews']);
        $this->assertEquals(2, $stats['summary']['books_read']);
        $this->assertEquals(3.7, round($stats['summary']['average_rating'], 1));

        $ratingDistribution = $stats['rating_distribution'];
        $this->assertEquals(0, $ratingDistribution[0]);
        $this->assertEquals(1, $ratingDistribution[1]);
        $this->assertEquals(0, $ratingDistribution[2]);
        $this->assertEquals(1, $ratingDistribution[3]);
        $this->assertEquals(1, $ratingDistribution[4]);

        $topBooks = $stats['top_rated_books'];
        $this->assertCount(2, $topBooks);
        $this->assertEquals($bookA->id, $topBooks[0]['id']);
        $this->assertEquals($bookB->id, $topBooks[1]['id']);

        $genreRatings = $stats['genre_ratings'];
        $this->assertCount(2, $genreRatings);
        $this->assertEquals('技術書', $genreRatings[0]['name']);
        $this->assertEquals(4.0, $genreRatings[0]['average_rating']);
        $this->assertEquals('小説', $genreRatings[1]['name']);
        $this->assertEquals(3.5, $genreRatings[1]['average_rating']);
    }
}
