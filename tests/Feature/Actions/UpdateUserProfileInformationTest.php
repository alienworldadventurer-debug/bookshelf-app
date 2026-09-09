<?php

namespace Tests\Feature\Actions;

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UpdateUserProfileInformationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * 正常系: 有効な名前とメールアドレスを指定して実行した際、
     * ユーザーのプロフィール情報（名前・メール）が正常に更新されること。
     */
    public function test_update_user_profile_information_action_updates_profile_successfully(): void
    {
        $user = User::factory()->create([
            'name' => '古い名前',
            'email' => 'old@example.com',
        ]);

        $action = new UpdateUserProfileInformation;

        // 正常系：プロフィールの更新
        $action->update($user, [
            'name' => '新しい名前',
            'email' => 'new@example.com',
        ]);

        // データベースの値が正しく書き換わっていることを検証
        $this->assertEquals('新しい名前', $user->fresh()->name);
        $this->assertEquals('new@example.com', $user->fresh()->email);
    }

    /**
     * @test
     * 異常系: 名前やメールが空、または不正なメール形式、他者と重複するメールアドレスを指定した際、
     * バリデーションエラー（ValidationException）が発生すること。
     */
    public function test_update_user_profile_information_action_fails_validation(): void
    {
        $user1 = User::factory()->create(['email' => 'user1@example.com']);
        User::factory()->create(['email' => 'user2@example.com']);

        $action = new UpdateUserProfileInformation;

        // 異常系1: 他のユーザーと重複するメールアドレスを指定した場合
        try {
            $action->update($user1, [
                'name' => 'ユーザー1',
                'email' => 'user2@example.com', // user2がすでに使用中のメールアドレス
            ]);
            $this->fail('メールアドレス重複時のバリデーションエラーが発生しませんでした。');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
        }

        // 異常系2: 必須項目（名前・メール）を空で指定した場合
        try {
            $action->update($user1, [
                'name' => '',
                'email' => '',
            ]);
            $this->fail('必須入力バリデーションエラーが発生しませんでした。');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('name', $e->errors());
            $this->assertArrayHasKey('email', $e->errors());
        }

        // 異常系3: メールアドレスの形式が不正な場合
        try {
            $action->update($user1, [
                'name' => 'ユーザー1',
                'email' => 'invalid-email-format', // 不正なフォーマット
            ]);
            $this->fail('メールアドレス形式バリデーションエラーが発生しませんでした。');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
        }
    }
}
