<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoExpireReadingPlanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * 期限を過ぎた進行中の読書計画がバッチ実行で期限切れになることを検証する。
     *
     * @return void 過去期限の計画だけがExpiredになり、当日期限の計画が維持されることを確認する
     */
    public function 期限を過ぎた進行中の計画がバッチ実行により自動失効になること(): void
    {
        $user = User::factory()->create();

        $book1 = Book::factory()->create();
        $book2 = Book::factory()->create();

        $yesterday = Carbon::yesterday();
        $today = Carbon::today();

        $expiredTarget = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book1->id,
            'target_date' => $yesterday,
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $notExpiredTarget = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book2->id,
            'target_date' => $today,
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $this->artisan('reading:send-reminders')
            ->assertExitCode(0);

        $this->assertEquals(ReadingPlanStatus::Expired, $expiredTarget->fresh()->status);

        $this->assertEquals(ReadingPlanStatus::InProgress, $notExpiredTarget->fresh()->status);
    }
}
