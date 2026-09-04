<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanReminder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * クラス ReEngagementReminderTest
 *
 * 毎日20:00のバッチ実行時に、
 * 自動失効からちょうど3日後（target_dateが3日前かつステータスがExpired）の読書計画を持つユーザーに対して、
 * 再エンゲージメント通知が正しく送信されることを検証する。
 */
class ReEngagementReminderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * 自動失効3日後の再エンゲージメント通知が正しく送信されることを検証する
     */
    public function 自動失効3日後の再エンゲージメント通知が正しく送信されること(): void
    {
        // --------------------------------------------------
        // Arrange (準備)
        // --------------------------------------------------
        // 🛠️ 通知送信をモック（擬似化）
        Notification::fake();

        $user = User::factory()->create();
        $book1 = Book::factory()->create();
        $book2 = Book::factory()->create();
        $book3 = Book::factory()->create();

        // ① 再エンゲージメント通知の対象データ
        // ステータス：Expired（期限切れ）、期日：3日前
        $reEngagementPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book1->id,
            'target_date' => Carbon::today()->subDays(3),
            'status' => ReadingPlanStatus::Expired,
        ]);

        // ② 対象外データA：ステータスはExpiredだが、期日が昨日（1日前 = まだ3日経っていない）
        $recentExpiredPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book2->id,
            'target_date' => Carbon::today()->subDays(1),
            'status' => ReadingPlanStatus::Expired,
        ]);

        // ③ 対象外データB：期日は3日前だが、すでに完了（Completed）している
        $completedPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book3->id,
            'target_date' => Carbon::today()->subDays(3),
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => Carbon::today()->subDays(3),
        ]);

        // --------------------------------------------------
        // Act (実行)
        // --------------------------------------------------
        // バッチ処理コマンドを実行
        $this->artisan('reading:send-reminders')
            ->assertExitCode(0);

        // --------------------------------------------------
        // Assert (検証)
        // --------------------------------------------------
        // ①の計画に対して、正しいタイミングキー（three_days_after）で通知が送信されたか検証
        Notification::assertSentTo(
            $user,
            ReadingPlanReminder::class,
            function ($notification) use ($reEngagementPlan) {
                return $notification->timing === 'three_days_after'
                    && $notification->readingPlan->id === $reEngagementPlan->id;
            }
        );

        // ②の計画（まだ3日経っていない）に対して、通知が送信されていないことを検証
        Notification::assertNotSentTo(
            $user,
            ReadingPlanReminder::class,
            function ($notification) use ($recentExpiredPlan) {
                return $notification->readingPlan->id === $recentExpiredPlan->id;
            }
        );

        // ③の計画（すでに完了済み）に対して、通知が送信されていないことを検証
        Notification::assertNotSentTo(
            $user,
            ReadingPlanReminder::class,
            function ($notification) use ($completedPlan) {
                return $notification->readingPlan->id === $completedPlan->id;
            }
        );
    }
}
