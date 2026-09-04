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
 * クラス SendReadingReminderTest
 *
 * 毎日20:00のバッチ実行時に、
 * 「期日の3日前」および「期日当日」の読書計画を持つユーザーに対して、
 * それぞれ正しいリマインダー通知が送信されることを検証する。
 */
class SendReadingReminderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * 期日の3日前と当日のリマインダー通知が正しく送信されることを検証する
     */
    public function 期日の3日前と当日のリマインダー通知が正しく送信されること(): void
    {
        // --------------------------------------------------
        // Arrange (準備)
        // --------------------------------------------------
        // 🛠️ 通知ファサードをモック（擬似化）し、実際の通知送信をストップする
        Notification::fake();

        $user = User::factory()->create();
        $book1 = Book::factory()->create();
        $book2 = Book::factory()->create();
        $book3 = Book::factory()->create();

        // ① 3日前のリマインダー対象（期日：3日後、ステータス：進行中）
        $threeDaysPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book1->id,
            'target_date' => Carbon::today()->addDays(3),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        // ② 当日のリマインダー対象（期日：本日、ステータス：進行中）
        $todayPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book2->id,
            'target_date' => Carbon::today(),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        // ③ 通知の対象外データ（期日：7日後、ステータス：進行中）
        $futurePlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book3->id,
            'target_date' => Carbon::today()->addDays(7),
            'status' => ReadingPlanStatus::InProgress,
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
        // ① 3日前リマインダーが、正しいタイミングキー（three_days_before）で送信されたか検証
        Notification::assertSentTo(
            $user,
            ReadingPlanReminder::class,
            function ($notification) use ($threeDaysPlan) {
                return $notification->timing === 'three_days_before'
                    && $notification->readingPlan->id === $threeDaysPlan->id;
            }
        );

        // ② 当日リマインダーが、正しいタイミングキー（on_due_date）で送信されたか検証
        Notification::assertSentTo(
            $user,
            ReadingPlanReminder::class,
            function ($notification) use ($todayPlan) {
                return $notification->timing === 'on_due_date'
                    && $notification->readingPlan->id === $todayPlan->id;
            }
        );

        // ③ 対象外（7日後）の計画に対して、通知が送信されていないことを検証
        Notification::assertNotSentTo(
            $user,
            ReadingPlanReminder::class,
            function ($notification) use ($futurePlan) {
                return $notification->readingPlan->id === $futurePlan->id;
            }
        );
    }
}
