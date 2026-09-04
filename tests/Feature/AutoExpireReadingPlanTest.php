<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * クラス AutoExpireReadingPlanTest
 *
 * 日次バッチ実行時、目標期限を過ぎても未完了（in_progress）の計画が、
 * 自動的に「expired（期限切れ）」に更新される仕様を検証する。
 */
class AutoExpireReadingPlanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * 期限を過ぎた進行中の計画がバッチ実行により自動失効（expired）になることを検証する
     */
    public function 期限を過ぎた進行中の計画がバッチ実行により自動失効になること(): void
    {
        // --------------------------------------------------
        // Arrange (準備)
        // --------------------------------------------------
        $user = User::factory()->create();

        // ジャンル付きの書籍などをファクトリで作成
        $book1 = Book::factory()->create();
        $book2 = Book::factory()->create();

        // 過去の日付（昨日以前）と、今日の日付を用意
        $yesterday = Carbon::yesterday();
        $today = Carbon::today();

        // ① 自動失効の対象データ：期限が昨日以前、かつ進行中(InProgress)
        $expiredTarget = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book1->id,
            'target_date' => $yesterday,
            'status' => ReadingPlanStatus::InProgress,
        ]);

        // ② 自動失効の対象外データ：期限が今日(本日中はまだセーフ)、進行中(InProgress)
        $notExpiredTarget = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book2->id,
            'target_date' => $today,
            'status' => ReadingPlanStatus::InProgress,
        ]);

        // --------------------------------------------------
        // Act (実行)
        // --------------------------------------------------
        // 自作したArtisanコマンドを実行し、正常終了(終了コード 0)することを検証
        $this->artisan('reading:send-reminders')
            ->assertExitCode(0);

        // --------------------------------------------------
        // Assert (検証)
        // --------------------------------------------------
        // ①の計画が、期待通り「Expired」に更新されているか
        $this->assertEquals(ReadingPlanStatus::Expired, $expiredTarget->fresh()->status);

        // ②の計画は、期待通り「InProgress」のままで維持されているか
        $this->assertEquals(ReadingPlanStatus::InProgress, $notExpiredTarget->fresh()->status);
    }
}
