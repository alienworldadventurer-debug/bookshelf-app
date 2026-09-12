<?php

namespace Tests\Feature\Actions;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ResetUserPasswordTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 有効なパスワードを指定すると、ユーザーのパスワードが更新されることを検証する。
     *
     * @return void 新しいパスワードがハッシュ化され、認証に利用できることを確認する
     */
    public function test_reset_user_password_action_resets_password_successfully(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $action = new ResetUserPassword;

        $action->reset($user, [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    /**
     * パスワード確認不一致や最小文字数未満の入力で、バリデーションエラーが発生することを検証する。
     *
     * @return void 不正な各入力に対してValidationExceptionが送出されることを確認する
     */
    public function test_reset_user_password_action_fails_validation_with_invalid_data(): void
    {
        $user = User::factory()->create();
        $action = new ResetUserPassword;

        try {
            $action->reset($user, [
                'password' => 'new-password',
                'password_confirmation' => 'mismatch-password',
            ]);
            $this->fail('パスワード確認不一致時のバリデーションエラーが発生しませんでした。');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('password', $e->errors());
        }

        try {
            $action->reset($user, [
                'password' => 'short',
                'password_confirmation' => 'short',
            ]);
            $this->fail('パスワード桁数不足時のバリデーションエラーが発生しませんでした。');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('password', $e->errors());
        }
    }
}
