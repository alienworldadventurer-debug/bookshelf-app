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
     * ログインユーザーが通知一覧を表示でき、自分宛ての通知が表示されることを検証する。
     *
     * @return void 通知一覧の表示内容を確認する
     */
    public function test_authenticated_user_can_view_notifications_index(): void
    {
        $user = User::factory()->create();

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
     * 未読通知を既読にすると、既読日時が保存され、成功メッセージとともにリダイレクトされることを検証する。
     *
     * @return void 通知の既読化、メッセージ、リダイレクト先を確認する
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

        $response = $this->actingAs($user)
            ->post(route('notifications.read', $notification->id));

        $response->assertRedirect();
        $response->assertSessionHas('success', '通知を既読にしました。');
        $this->assertNotNull($notification->fresh()->read_at);
    }

    /**
     * 他のユーザーの通知を既読にしようとすると403 Forbiddenが返り、通知が変更されないことを検証する。
     *
     * @return void 他人の通知へのアクセス制限と既読日時の未変更を確認する
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

        $response = $this->actingAs($user)
            ->post(route('notifications.read', $otherNotification->id));

        $response->assertStatus(403);
        $this->assertNull($otherNotification->fresh()->read_at);
    }

    /**
     * ゲストユーザーが通知一覧と既読処理へアクセスするとログイン画面にリダイレクトされることを検証する。
     *
     * @return void 未認証アクセスが保護されていることを確認する
     */
    public function test_guest_cannot_access_notifications(): void
    {
        $responseIndex = $this->get(route('notifications.index'));
        $responseIndex->assertRedirect(route('login'));

        $dummyId = (string) Str::uuid();
        $responseRead = $this->post(route('notifications.read', $dummyId));
        $responseRead->assertRedirect(route('login'));
    }
}
