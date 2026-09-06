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
     * ログインユーザーは、自身の作成した読書計画のみを一覧表示できることを検証します。
     */
    public function test_user_can_view_own_reading_plans_only(): void
    {
        // Arrange (準備)
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

        // Act (実行)
        $response = $this->actingAs($user)->get(route('reading-plans.index'));

        // Assert (検証)
        $response->assertStatus(200);
        $response->assertSee($book->title);
        $response->assertViewHas('readingPlans', function ($plans) use ($ownPlan, $otherPlan) {
            return $plans->contains($ownPlan) && ! $plans->contains($otherPlan);
        });
    }

    /**
     * 読書計画一覧がステータスで正しくフィルタリングされることを検証します。
     */
    public function test_index_filters_reading_plans_by_status(): void
    {
        // Arrange (準備)
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

        // Act (実行)
        $response = $this->actingAs($user)->get(route('reading-plans.index', ['status' => 'completed']));

        // Assert (検証)
        $response->assertStatus(200);
        $response->assertViewHas('readingPlans', function ($plans) use ($inProgressPlan, $completedPlan) {
            return ! $plans->contains($inProgressPlan) && $plans->contains($completedPlan);
        });
    }

    /**
     * 未ログインのゲストユーザーが読書計画一覧にアクセスした際、ログイン画面へリダイレクトされることを検証します。
     */
    public function test_guest_is_redirected_to_login_on_index(): void
    {
        // Act (実行)
        $response = $this->get(route('reading-plans.index'));

        // Assert (検証)
        $response->assertRedirect(route('login'));
    }

    /**
     * ログインユーザーは、有効なデータを用いて読書計画を登録できることを検証します。
     */
    public function test_user_can_store_reading_plan_with_valid_data(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $targetDate = Carbon::tomorrow()->format('Y-m-d');

        // Act (実行)
        $response = $this->actingAs($user)->post(route('reading-plans.store'), [
            'book_id' => $book->id,
            'target_date' => $targetDate,
            'status' => 'in_progress',
        ]);

        // Assert (検証)
        $response->assertRedirect(route('reading-plans.index'));
        $response->assertSessionHas('success', '読書計画を登録しました。');

        // データベースに保存された日付形式が 'Y-m-d 00:00:00' のようになっている可能性があるため、Carbonでフォーマットを揃えて検証します
        $this->assertDatabaseHas('reading_plans', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'target_date' => Carbon::parse($targetDate)->startOfDay()->toDateTimeString(),
            'status' => ReadingPlanStatus::InProgress,
        ]);
    }

    /**
     * 目標期日に過去の日付を指定した際、登録処理がバリデーションエラー（本日以降が必要）となることを検証します。
     */
    public function test_store_validation_fails_on_past_target_date(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $pastDate = Carbon::yesterday()->format('Y-m-d');

        // Act (実行)
        $response = $this->actingAs($user)->post(route('reading-plans.store'), [
            'book_id' => $book->id,
            'target_date' => $pastDate,
            'status' => 'in_progress',
        ]);

        // Assert (検証)
        $response->assertSessionHasErrors(['target_date']);
        $this->assertDatabaseMissing('reading_plans', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    /**
     * 同一ユーザーが、同じ書籍ですでに「進行中（in_progress）」の計画を持っている場合、重複登録が防がれることを検証します。
     */
    public function test_store_prevent_duplicate_in_progress_plan(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // 既存の進行中計画を作成
        ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::InProgress,
        ]);

        // Act (実行)
        $response = $this->actingAs($user)->post(route('reading-plans.store'), [
            'book_id' => $book->id,
            'target_date' => Carbon::tomorrow()->format('Y-m-d'),
            'status' => 'in_progress',
        ]);

        // Assert (検証)
        $response->assertSessionHasErrors(['book_id']);
    }

    /**
     * 「期限切れ（expired）」状態の計画を編集して期日を未来に設定した場合、
     * ステータスが自動的に「進行中（in_progress）」に復帰することを検証します。
     * フォームおよび要件に合わせ、リクエストパラメータには 'status' を含めず 'target_date' のみを送信します。
     */
    public function test_update_automatically_restores_expired_plan_to_in_progress(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $plan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::Expired,
            'target_date' => Carbon::yesterday()->startOfDay()->toDateTimeString(),
        ]);

        $futureDate = Carbon::tomorrow()->format('Y-m-d');

        // Act (実行) - ステータスは渡さず、期日のみを変更して送信
        $response = $this->actingAs($user)->put(route('reading-plans.update', $plan), [
            'target_date' => $futureDate,
        ]);

        // Assert (検証)
        $response->assertRedirect(route('reading-plans.index'));
        $response->assertSessionHas('success', '読書計画を更新しました。');

        $this->assertDatabaseHas('reading_plans', [
            'id' => $plan->id,
            'target_date' => Carbon::parse($futureDate)->startOfDay()->toDateTimeString(),
            'status' => ReadingPlanStatus::InProgress, // 自動的に進行中（InProgress）へ復帰していること
        ]);
    }

    /**
     * すでに完了した（completed）読書計画の編集画面へのアクセス、および更新処理が拒否されることを検証します。
     */
    public function test_cannot_edit_or_update_completed_reading_plan(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $plan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::Completed,
        ]);

        // Act (実行) - 編集画面アクセス（302リダイレクトで一覧に戻され、エラーセッションを保持）
        $responseEdit = $this->actingAs($user)->get(route('reading-plans.edit', $plan));

        // Assert (検証)
        $responseEdit->assertRedirect(route('reading-plans.index'));
        $responseEdit->assertSessionHas('error', '完了した読書計画は編集できません。');

        // Act (実行) - 直接PUT更新リクエスト（403 Forbidden）
        $responseUpdate = $this->actingAs($user)->put(route('reading-plans.update', $plan), [
            'target_date' => Carbon::tomorrow()->format('Y-m-d'),
        ]);

        // Assert (検証)
        $responseUpdate->assertStatus(403);
    }

    /**
     * ログインユーザーは、自身の読書計画を読了完了状態に遷移させられることを検証します。
     */
    public function test_user_can_complete_reading_plan(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $plan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::InProgress,
        ]);

        // Act (実行) - フォームに則りPOSTで送信
        $response = $this->actingAs($user)->post(route('reading-plans.complete', $plan));

        // Assert (検証)
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
     * ログインユーザーは、自身の読書計画を物理削除できることを検証します。
     */
    public function test_user_can_delete_reading_plan(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $plan = ReadingPlan::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::InProgress,
        ]);

        // Act (実行)
        $response = $this->actingAs($user)->delete(route('reading-plans.destroy', $plan));

        // Assert (検証)
        $response->assertRedirect(route('reading-plans.index'));
        $response->assertSessionHas('success', '読書計画を削除しました。');

        $this->assertDatabaseMissing('reading_plans', [
            'id' => $plan->id,
        ]);
    }

    /**
     * 他人の読書計画に対して「編集、更新、読了、削除」の操作を試みた場合、
     * すべて 403 Forbidden で認可エラーになることを検証します。
     */
    public function test_cannot_operate_other_users_reading_plan(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create();

        $otherPlan = ReadingPlan::factory()->create([
            'user_id' => $otherUser->id,
            'book_id' => $book->id,
            'status' => ReadingPlanStatus::InProgress,
        ]);

        // 1. 他人の計画の編集画面 (edit)
        $responseEdit = $this->actingAs($user)->get(route('reading-plans.edit', $otherPlan));
        $responseEdit->assertStatus(403);

        // 2. 他人の計画の更新 (update)
        $responseUpdate = $this->actingAs($user)->put(route('reading-plans.update', $otherPlan), [
            'target_date' => Carbon::tomorrow()->format('Y-m-d'),
        ]);
        $responseUpdate->assertStatus(403);

        // 3. 他人の計画の読了 (complete)
        $responseComplete = $this->actingAs($user)->post(route('reading-plans.complete', $otherPlan));
        $responseComplete->assertStatus(403);

        // 4. 他人の計画の削除 (destroy)
        $responseDelete = $this->actingAs($user)->delete(route('reading-plans.destroy', $otherPlan));
        $responseDelete->assertStatus(403);
    }
}
