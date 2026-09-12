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

class SendReadingReminderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * 期日の3日前と当日の読書計画に、対応するリマインダー通知が送信されることを検証する。
     *
     * @return void 通知のタイミングと対象外計画への未送信を確認する
     */
    public function 期日の3日前と当日のリマインダー通知が正しく送信されること(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $book1 = Book::factory()->create();
        $book2 = Book::factory()->create();
        $book3 = Book::factory()->create();

        $threeDaysPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book1->id,
            'target_date' => Carbon::today()->addDays(3),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $todayPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book2->id,
            'target_date' => Carbon::today(),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $futurePlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book3->id,
            'target_date' => Carbon::today()->addDays(7),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $this->artisan('reading:send-reminders')
            ->assertExitCode(0);

        Notification::assertSentTo(
            $user,
            ReadingPlanReminder::class,
            function (ReadingPlanReminder $notification) use ($threeDaysPlan): bool {
                return $notification->timing === 'three_days_before'
                    && $notification->readingPlan->id === $threeDaysPlan->id;
            }
        );

        Notification::assertSentTo(
            $user,
            ReadingPlanReminder::class,
            function (ReadingPlanReminder $notification) use ($todayPlan): bool {
                return $notification->timing === 'on_due_date'
                    && $notification->readingPlan->id === $todayPlan->id;
            }
        );

        Notification::assertNotSentTo(
            $user,
            ReadingPlanReminder::class,
            function (ReadingPlanReminder $notification) use ($futurePlan): bool {
                return $notification->readingPlan->id === $futurePlan->id;
            }
        );
    }
}
