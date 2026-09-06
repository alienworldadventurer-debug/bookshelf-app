<?php

namespace Tests\Feature\Models;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * クラス ReadingPlanTest
 *
 * ReadingPlan モデルのリレーションシップを検証する単体（Unit）テスト。
 */
class ReadingPlanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * ReadingPlanモデルから、紐づく「user（計画作成者）」が正確に取得できること。
     */
    public function test_reading_plan_belongs_to_user(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $plan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
        ]);

        // Act & Assert (実行と検証)
        $this->assertInstanceOf(User::class, $plan->user);
        $this->assertEquals($user->id, $plan->user->id);
    }

    /**
     * @test
     * ReadingPlanモデルから、紐づく「book（対象の書籍）」が正確に取得できること。
     */
    public function test_reading_plan_belongs_to_book(): void
    {
        // Arrange (準備)
        $book = Book::factory()->create();
        $plan = ReadingPlan::factory()->create([
            'book_id' => $book->id,
        ]);

        // Act & Assert (実行と検証)
        $this->assertInstanceOf(Book::class, $plan->book);
        $this->assertEquals($book->id, $plan->book->id);
    }
}
