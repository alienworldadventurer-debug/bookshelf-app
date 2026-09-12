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
     * 有効な名前とメールアドレスを指定すると、プロフィール情報が更新されることを検証する。
     *
     * @return void 更新後の名前とメールアドレスがデータベースに保存されることを確認する
     */
    public function test_update_user_profile_information_action_updates_profile_successfully(): void
    {
        $user = User::factory()->create([
            'name' => '古い名前',
            'email' => 'old@example.com',
        ]);

        $action = new UpdateUserProfileInformation;

        $action->update($user, [
            'name' => '新しい名前',
            'email' => 'new@example.com',
        ]);

        $this->assertEquals('新しい名前', $user->fresh()->name);
        $this->assertEquals('new@example.com', $user->fresh()->email);
    }

    /**
     * 重複・空欄・形式不正のプロフィール情報で、バリデーションエラーが発生することを検証する。
     *
     * @return void 各不正入力に対してValidationExceptionが送出されることを確認する
     */
    public function test_update_user_profile_information_action_fails_validation(): void
    {
        $user1 = User::factory()->create(['email' => 'user1@example.com']);
        User::factory()->create(['email' => 'user2@example.com']);

        $action = new UpdateUserProfileInformation;

        try {
            $action->update($user1, [
                'name' => 'ユーザー1',
                'email' => 'user2@example.com',
            ]);
            $this->fail('メールアドレス重複時のバリデーションエラーが発生しませんでした。');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
        }

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

        try {
            $action->update($user1, [
                'name' => 'ユーザー1',
                'email' => 'invalid-email-format',
            ]);
            $this->fail('メールアドレス形式バリデーションエラーが発生しませんでした。');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
        }
    }
}
