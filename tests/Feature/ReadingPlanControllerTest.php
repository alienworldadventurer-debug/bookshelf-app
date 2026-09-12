<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ログインユーザーが自身の読書計画だけを一覧表示できることを検証する。
     *
     * @return void 自分の計画が表示され、他人の計画が除外されることを確認する
     */
    public function test_user_can_view_own_reading_plans_only(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $book = Book::factory()->create();

        $ownPlan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $otherPlan = ReadingPlan::factory()->create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $response = $this->actingAs($user)->get(route('reading-plans.index'));

        $response->assertStatus(200);
        $response->assertSee($book->title);
        $response->assertViewHas('readingPlans', function (mixed $plans) use ($ownPlan, $otherPlan): bool {
            return $plans->contains($ownPlan) && ! $plans->contains($otherPlan);
        });
    }

    /**
     * 読書計画一覧が指定したステータスで正しく絞り込まれることを検証する。
     *
     * @return void 指定ステータスの計画だけが表示されることを確認する
     */
    public function test_index_filters_reading_plans_by_status(): void
    {
        $user = User::factory()->create();
        $book1 = Book::factory()->create();
        $book2 = Book::factory()->create();

        $inProgressPlan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book1->id,
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $completedPlan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book2->id,
            'status' => ReadingPlanStatus::Completed,
        ]);

        $response = $this->actingAs($user)->get(route('reading-plans.index', ['status' => 'completed']));

        $response->assertStatus(200);
        $response->assertViewHas('readingPlans', function (mixed $plans) use ($inProgressPlan, $completedPlan): bool {
            return ! $plans->contains($inProgressPlan) && $plans->contains($completedPlan);
        });
    }

    /**
     * ゲストユーザーが読書計画一覧にアクセスするとログイン画面へリダイレクトされることを検証する。
     *
     * @return void 未認証アクセスのリダイレクト先を確認する
     */
    public function test_guest_is_redirected_to_login_on_index(): void
    {
        $response = $this->get(route('reading-plans.index'));

        $response->assertRedirect(route('login'));
    }

    /**
     * ログインユーザーが有効なデータで読書計画を登録できることを検証する。
     *
     * @return void 計画の保存、成功メッセージ、一覧へのリダイレクトを確認する
     */
    public function test_user_can_store_reading_plan_with_valid_data(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $targetDate = Carbon::tomorrow()->format('Y-m-d');

        $response = $this->actingAs($user)->post(route('reading-plans.store'), [
            'book_id' => $book->id,
            'target_date' => $targetDate,
            'status' => 'in_progress',
        ]);

        $response->assertRedirect(route('reading-plans.index'));
        $response->assertSessionHas('success', '読書計画を登録しました。');

        $this->assertDatabaseHas('reading_plans', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => Carbon::parse($targetDate)->startOfDay()->toDateTimeString(),
            'status' => ReadingPlanStatus::InProgress,
        ]);
    }

    /**
     * 目標期日に過去の日付を指定するとバリデーションエラーになることを検証する。
     *
     * @return void 過去の日付が拒否され、計画が保存されないことを確認する
     */
    public function test_store_validation_fails_on_past_target_date(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $pastDate = Carbon::yesterday()->format('Y-m-d');

        $response = $this->actingAs($user)->post(route('reading-plans.store'), [
            'book_id' => $book->id,
            'target_date' => $pastDate,
            'status' => 'in_progress',
        ]);

        $response->assertSessionHasErrors(['target_date']);
        $this->assertDatabaseMissing('reading_plans', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    /**
     * 同じ書籍に進行中の計画がある場合、重複登録が防止されることを検証する。
     *
     * @return void 書籍に対する重複登録のバリデーションエラーを確認する
     */
    public function test_store_prevent_duplicate_in_progress_plan(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $response = $this->actingAs($user)->post(route('reading-plans.store'), [
            'book_id' => $book->id,
            'target_date' => Carbon::tomorrow()->format('Y-m-d'),
            'status' => 'in_progress',
        ]);

        $response->assertSessionHasErrors(['book_id']);
    }

    /**
     * 「期限切れ（expired）」状態の計画を編集して期日を未来に設定した場合、
     * ステータスが自動的に「進行中（in_progress）」に復帰することを検証する。
     *
     * @return void 期日の更新、ステータスの復帰、成功メッセージを確認する
     */
    public function test_update_automatically_restores_expired_plan_to_in_progress(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $plan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::Expired,
            'target_date' => Carbon::yesterday()->startOfDay()->toDateTimeString(),
        ]);

        $futureDate = Carbon::tomorrow()->format('Y-m-d');

        $response = $this->actingAs($user)->put(route('reading-plans.update', $plan), [
            'target_date' => $futureDate,
        ]);

        $response->assertRedirect(route('reading-plans.index'));
        $response->assertSessionHas('success', '読書計画を更新しました。');

        $this->assertDatabaseHas('reading_plans', [
            'id' => $plan->id,
            'target_date' => Carbon::parse($futureDate)->startOfDay()->toDateTimeString(),
            'status' => ReadingPlanStatus::InProgress,
        ]);
    }

    /**
     * 完了済みの読書計画に対する編集画面へのアクセスと更新処理が拒否されることを検証する。
     *
     * @return void 編集画面のリダイレクトと更新時の403応答を確認する
     */
    public function test_cannot_edit_or_update_completed_reading_plan(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $plan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::Completed,
        ]);

        $responseEdit = $this->actingAs($user)->get(route('reading-plans.edit', $plan));

        $responseEdit->assertRedirect(route('reading-plans.index'));
        $responseEdit->assertSessionHas('error', '完了した読書計画は編集できません。');

        $responseUpdate = $this->actingAs($user)->put(route('reading-plans.update', $plan), [
            'target_date' => Carbon::tomorrow()->format('Y-m-d'),
        ]);

        $responseUpdate->assertStatus(403);
    }

    /**
     * ログインユーザーが自身の読書計画を完了状態に変更できることを検証する。
     *
     * @return void ステータス、完了日時、成功メッセージを確認する
     */
    public function test_user_can_complete_reading_plan(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $plan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $response = $this->actingAs($user)->post(route('reading-plans.complete', $plan));

        $response->assertRedirect(route('reading-plans.index'));
        $response->assertSessionHas('success', '読書計画を完了しました。');

        $this->assertDatabaseHas('reading_plans', [
            'id' => $plan->id,
            'status' => ReadingPlanStatus::Completed,
        ]);

        $updatedPlan = ReadingPlan::find($plan->id);
        $this->assertNotNull($updatedPlan->completed_at);
    }

    /**
     * ログインユーザーが自身の読書計画を削除できることを検証する。
     *
     * @return void 削除処理とデータベースからの消去を確認する
     */
    public function test_user_can_delete_reading_plan(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $plan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $response = $this->actingAs($user)->delete(route('reading-plans.destroy', $plan));

        $response->assertRedirect(route('reading-plans.index'));
        $response->assertSessionHas('success', '読書計画を削除しました。');

        $this->assertDatabaseMissing('reading_plans', [
            'id' => $plan->id,
        ]);
    }

    /**
     * 他人の読書計画に対して「編集、更新、読了、削除」の操作を試みた場合、
     * すべて403 Forbiddenで認可エラーになることを検証する。
     *
     * @return void 他人の計画に対する各操作が拒否されることを確認する
     */
    public function test_cannot_operate_other_users_reading_plan(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create();

        $otherPlan = ReadingPlan::factory()->create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::InProgress,
        ]);

        $responseEdit = $this->actingAs($user)->get(route('reading-plans.edit', $otherPlan));
        $responseEdit->assertStatus(403);

        $responseUpdate = $this->actingAs($user)->put(route('reading-plans.update', $otherPlan), [
            'target_date' => Carbon::tomorrow()->format('Y-m-d'),
        ]);
        $responseUpdate->assertStatus(403);

        $responseComplete = $this->actingAs($user)->post(route('reading-plans.complete', $otherPlan));
        $responseComplete->assertStatus(403);

        $responseDelete = $this->actingAs($user)->delete(route('reading-plans.destroy', $otherPlan));
        $responseDelete->assertStatus(403);
    }
}
