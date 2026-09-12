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

class ReEngagementReminderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * 自動失効から3日後の読書計画に再エンゲージメント通知が送信されることを検証する。
     *
     * @return void 対象計画だけが通知対象になり、通知内容も正しいことを確認する
     */
    public function 自動失効3日後の再エンゲージメント通知が正しく送信されること(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $book1 = Book::factory()->create();
        $book2 = Book::factory()->create();
        $book3 = Book::factory()->create();

        $reEngagementPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book1->id,
            'target_date' => Carbon::today()->subDays(3),
            'status' => ReadingPlanStatus::Expired,
        ]);

        $recentExpiredPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book2->id,
            'target_date' => Carbon::today()->subDays(1),
            'status' => ReadingPlanStatus::Expired,
        ]);

        $completedPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book3->id,
            'target_date' => Carbon::today()->subDays(3),
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => Carbon::today()->subDays(3),
        ]);

        $this->artisan('reading:send-reminders')
            ->assertExitCode(0);

        Notification::assertSentTo(
            $user,
            ReadingPlanReminder::class,
            function (ReadingPlanReminder $notification) use ($reEngagementPlan): bool {
                return $notification->timing === 'three_days_after'
                    && $notification->readingPlan->id === $reEngagementPlan->id;
            }
        );

        Notification::assertNotSentTo(
            $user,
            ReadingPlanReminder::class,
            function (ReadingPlanReminder $notification) use ($recentExpiredPlan): bool {
                return $notification->readingPlan->id === $recentExpiredPlan->id;
            }
        );

        Notification::assertNotSentTo(
            $user,
            ReadingPlanReminder::class,
            function (ReadingPlanReminder $notification) use ($completedPlan): bool {
                return $notification->readingPlan->id === $completedPlan->id;
            }
        );
    }
}
