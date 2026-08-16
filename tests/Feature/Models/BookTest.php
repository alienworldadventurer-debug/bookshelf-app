<?php

namespace Tests\Feature\Models;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $book->user);
        $this->assertEquals($user->id, $book->user->id);
    }

    public function test_book_belongs_to_many_genres(): void
    {
        $book = Book::factory()->create();
        $genres = Genre::factory()->count(2)->create();

        $book->genres()->attach($genres->pluck('id')->toArray());

        $this->assertCount(2, $book->genres);
        $this->assertTrue($book->genres->contains($genres->first()));
    }

    public function test_book_has_many_reviews(): void
    {
        $book = Book::factory()->create();
        Review::factory()->count(3)->create(['book_id' => $book->id]);

        $this->assertCount(3, $book->reviews);
    }

    public function test_book_calculates_avg_rating_and_review_count(): void
    {
        $book = Book::factory()->create();
        Review::factory()->create([
            'book_id' => $book->id,
            'rating' => 3,
        ]);
        Review::factory()->create([
            'book_id' => $book->id,
            'rating' => 5,
        ]);

        $avgRating = $book->reviews()->avg('rating');
        $count = $book->reviews()->count();

        $this->assertEquals(4.0, $avgRating);
        $this->assertEquals(2, $count);
    }
}
