<?php

namespace Tests\Feature\Models;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 読書計画が作成したユーザーに属することを検証する。
     *
     * @return void userリレーションが対象ユーザーを返すことを確認する
     */
    public function test_reading_plan_belongs_to_user(): void
    {
        $user = User::factory()->create();

        $plan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $plan->user);

        $this->assertEquals($user->id, $plan->user->id);
    }

    /**
     * 読書計画が対象の書籍に属することを検証する。
     *
     * @return void bookリレーションが対象書籍を返すことを確認する
     */
    public function test_reading_plan_belongs_to_book(): void
    {
        $book = Book::factory()->create();

        $plan = ReadingPlan::factory()->create([
            'book_id' => $book->id,
        ]);

        $this->assertInstanceOf(Book::class, $plan->book);

        $this->assertEquals($book->id, $plan->book->id);
    }

    /**
     * 読書計画が期限切れかどうかを状態と日付から判定することを検証する。
     *
     * @return void 期限切れ、当日期限、完了済みの判定結果を確認する
     */
    public function test_reading_plan_is_overdue_logic(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $overduePlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => Carbon::yesterday(),
            'status' => ReadingPlanStatus::InProgress,
        ]);
        $this->assertTrue($overduePlan->isOverdue());

        $todayPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => Book::factory()->create()->id,
            'target_date' => Carbon::today(),
            'status' => ReadingPlanStatus::InProgress,
        ]);
        $this->assertFalse($todayPlan->isOverdue());

        $completedPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => Book::factory()->create()->id,
            'target_date' => Carbon::yesterday(),
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => Carbon::yesterday(),
        ]);
        $this->assertFalse($completedPlan->isOverdue());
    }

    /**
     * 読書計画の各クエリスコープが対象の状態や日付で絞り込むことを検証する。
     *
     * @return void inProgress、completed、expired、overdueの抽出結果を確認する
     */
    public function test_reading_plan_query_scopes_filter_by_status_and_date(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $inProgressPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => Carbon::yesterday(),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $completedPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => Book::factory()->create()->id,
            'target_date' => Carbon::today(),
            'status' => ReadingPlanStatus::Completed,
        ]);

        $expiredPlan = ReadingPlan::create([
            'user_id' => $user->id,
            'book_id' => Book::factory()->create()->id,
            'target_date' => Carbon::yesterday(),
            'status' => ReadingPlanStatus::Expired,
        ]);

        $this->assertTrue(ReadingPlan::inProgress()->get()->contains($inProgressPlan));
        $this->assertFalse(ReadingPlan::inProgress()->get()->contains($completedPlan));
        $this->assertTrue(ReadingPlan::completed()->get()->contains($completedPlan));
        $this->assertTrue(ReadingPlan::expired()->get()->contains($expiredPlan));
        $this->assertTrue(ReadingPlan::overdue()->get()->contains($inProgressPlan));
    }
}
