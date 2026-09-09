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
     * @test
     * 正常系: 有効な新しいパスワード（8文字以上かつ確認用と一致）を指定して実行した際、
     * ユーザーのパスワードが安全にハッシュ化されて更新されること。
     */
    public function test_reset_user_password_action_resets_password_successfully(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $action = new ResetUserPassword;

        // 正常系：パスワードリセットの実行 (8文字以上の同一パスワード)
        $action->reset($user, [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        // パスワードが更新され、新パスワードでハッシュが一致することを検証
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    /**
     * @test
     * 異常系: 不一致なパスワードや8文字未満などの不正な入力値を指定した際、
     * バリデーションエラー（ValidationException）が発生すること。
     */
    public function test_reset_user_password_action_fails_validation_with_invalid_data(): void
    {
        $user = User::factory()->create();
        $action = new ResetUserPassword;

        // 異常系1: パスワード確認用不一致
        try {
            $action->reset($user, [
                'password' => 'new-password',
                'password_confirmation' => 'mismatch-password',
            ]);
            $this->fail('パスワード確認不一致時のバリデーションエラーが発生しませんでした。');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('password', $e->errors());
        }

        // 異常系2: パスワードが8文字未満
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
