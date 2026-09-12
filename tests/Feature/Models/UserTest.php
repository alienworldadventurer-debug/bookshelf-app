<?php

namespace Tests\Feature\Models;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ユーザーが複数の書籍を所有できることを検証する。
     *
     * @return void ユーザーから2件の書籍を取得できることを確認する
     */
    public function test_user_has_many_books(): void
    {
        $user = User::factory()->create();
        Book::factory()->count(2)->create(['user_id' => $user->id]);

        $this->assertCount(2, $user->books);
    }

    /**
     * ユーザーが複数のレビューを投稿できることを検証する。
     *
     * @return void ユーザーから3件のレビューを取得できることを確認する
     */
    public function test_user_has_many_reviews(): void
    {
        $user = User::factory()->create();
        Review::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertCount(3, $user->reviews);
    }

    /**
     * ユーザーがお気に入り書籍を複数登録できることを検証する。
     *
     * @return void 紐付けた2件の書籍を取得できることを確認する
     */
    public function test_user_has_many_favorite_books(): void
    {
        $user = User::factory()->create();
        $books = Book::factory()->count(2)->create();

        $user->favoriteBooks()->attach($books->pluck('id')->toArray());

        $this->assertCount(2, $user->favoriteBooks);
        $this->assertTrue($user->favoriteBooks->contains($books->first()));
    }

    /**
     * ユーザーが複数の読書計画を持てることを検証する。
     *
     * @return void 2件の読書計画を取得でき、要素がReadingPlanであることを確認する
     */
    public function test_user_has_many_reading_plans(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        ReadingPlan::factory()->count(2)->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        $this->assertCount(2, $user->readingPlans);

        $this->assertInstanceOf(ReadingPlan::class, $user->readingPlans->first());
    }
}
