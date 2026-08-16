<?php

namespace Tests\Feature\Models;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $review->user);
        $this->assertEquals($user->id, $review->user->id);
    }

    public function test_review_belongs_to_book(): void
    {
        $book = Book::factory()->create();
        $review = Review::factory()->create(['book_id' => $book->id]);

        $this->assertInstanceOf(Book::class, $review->book);
        $this->assertEquals($book->id, $review->book->id);
    }

    public function test_review_is_liked_by_many_users(): void
    {
        $review = Review::factory()->create();
        $users = User::factory()->count(2)->create();

        // 中間テーブル（review_likes）への紐付けテスト
        $review->likedByUsers()->attach($users->pluck('id')->toArray());

        $this->assertCount(2, $review->likedByUsers);
        $this->assertTrue($review->likedByUsers->contains($users->first()));
    }
}
