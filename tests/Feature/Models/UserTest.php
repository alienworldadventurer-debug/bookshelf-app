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

    public function test_user_has_many_books(): void
    {
        $user = User::factory()->create();
        Book::factory()->count(2)->create(['user_id' => $user->id]);

        $this->assertCount(2, $user->books);
    }

    public function test_user_has_many_reviews(): void
    {
        $user = User::factory()->create();
        Review::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertCount(3, $user->reviews);
    }

    public function test_user_has_many_favorite_books(): void
    {
        $user = User::factory()->create();
        $books = Book::factory()->count(2)->create();

        // お気に入り中間テーブル（favorites）への紐付け
        $user->favoriteBooks()->attach($books->pluck('id')->toArray());

        $this->assertCount(2, $user->favoriteBooks);
        $this->assertTrue($user->favoriteBooks->contains($books->first()));
    }

    /**
     * Userモデルから、追加された「readingPlans（読書計画）」の
     * 関連リレーションデータが正確に取得できることを検証します。
     */
    public function test_user_has_many_reading_plans(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // 読書計画を2件登録
        ReadingPlan::factory()->count(2)->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        // Assert (検証)
        // 1. readingPlansリレーションから取得したコレクション数が2であることを検証
        $this->assertCount(2, $user->readingPlans);

        // 2. リレーションから返されるデータが ReadingPlan クラスのインスタンスであることを検証
        $this->assertInstanceOf(ReadingPlan::class, $user->readingPlans->first());
    }
}
