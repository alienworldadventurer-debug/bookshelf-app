<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * 正常系: ログインユーザーが通知一覧画面（/notifications）にアクセスした際、
     * 自分宛ての通知一覧が正しく表示されること。
     */
    public function test_authenticated_user_can_view_notifications_index(): void
    {
        $user = User::factory()->create();

        // テスト用通知データを作成
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\ReadingPlanReminder',
            'data' => [
                'title' => '【リマインダー】読書期日の3日前です',
                'body' => '計画的に読み進めましょう！',
                'timing' => 'three_days_before',
            ],
            'read_at' => null,
        ]);

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertStatus(200);
        $response->assertViewHas('notifications');
        $response->assertSee('【リマインダー】読書期日の3日前です');
    }

    /**
     * @test
     * 正常系: 未読通知に対して「既読にする」ボタンを押下した際、
     * read_at に現在日時がセットされ、成功メッセージとともに一覧へリダイレクトバックされること。
     */
    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();

        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\ReadingPlanReminder',
            'data' => [
                'title' => '【リマインダー】読書期日の3日前です',
                'body' => 'テストメッセージ',
            ],
            'read_at' => null,
        ]);

        // 既読化処理（POST /notifications/{id}/read）を実行
        $response = $this->actingAs($user)
            ->post(route('notifications.read', $notification->id));

        $response->assertRedirect();
        $response->assertSessionHas('success', '通知を既読にしました。');

        // DBの read_at カラムに現在日時がセットされているか検証
        $this->assertNotNull($notification->fresh()->read_at);
    }

    /**
     * @test
     * 異常系: 他人の通知を既読にしようとした場合、
     * 認可エラーが発生し HTTP 403 Forbidden が返ること。
     */
    public function test_user_cannot_mark_other_users_notification_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $otherNotification = $otherUser->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\ReadingPlanReminder',
            'data' => [
                'title' => '他人の通知',
            ],
            'read_at' => null,
        ]);

        // 別ユーザーでログインして他人の通知の既読処理を試みる
        $response = $this->actingAs($user)
            ->post(route('notifications.read', $otherNotification->id));

        $response->assertStatus(403);
        $this->assertNull($otherNotification->fresh()->read_at);
    }

    /**
     * @test
     * 異常系: 未認証（ゲスト）ユーザーが通知一覧や既読処理へアクセスした際、
     * ログイン画面（/login）へ自動リダイレクトされること。
     */
    public function test_guest_cannot_access_notifications(): void
    {
        // ① 一覧画面へのアクセス制限
        $responseIndex = $this->get(route('notifications.index'));
        $responseIndex->assertRedirect(route('login'));

        // ② 既読化処理へのアクセス制限
        $dummyId = (string) Str::uuid();
        $responseRead = $this->post(route('notifications.read', $dummyId));
        $responseRead->assertRedirect(route('login'));
    }
}
