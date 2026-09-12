<?php

namespace Tests\Feature\Actions;

use App\Actions\Fortify\UpdateUserPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UpdateUserPasswordTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 正しい現在のパスワードと有効な新しいパスワードを指定すると、パスワードが更新されることを検証する。
     *
     * @return void 新しいパスワードがハッシュ化され、認証に利用できることを確認する
     */
    public function test_update_user_password_action_updates_password_successfully(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
        ]);

        $action = new UpdateUserPassword;

        $this->actingAs($user);

        $action->update($user, [
            'current_password' => 'current-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    /**
     * 現在のパスワード誤りや新しいパスワードの不正な入力で、バリデーションエラーが発生することを検証する。
     *
     * @return void 各不正入力に対してValidationExceptionが送出されることを確認する
     */
    public function test_update_user_password_action_fails_validation(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $action = new UpdateUserPassword;

        try {
            $action->update($user, [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);
            $this->fail('現在のパスワード誤り時のバリデーションエラーが発生しませんでした。');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('current_password', $e->errors());
        }

        try {
            $action->update($user, [
                'current_password' => 'correct-password',
                'password' => 'new-password',
                'password_confirmation' => 'mismatch-password',
            ]);
            $this->fail('新規パスワード不一致時のバリデーションエラーが発生しませんでした。');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('password', $e->errors());
        }

        try {
            $action->update($user, [
                'current_password' => 'correct-password',
                'password' => 'short',
                'password_confirmation' => 'short',
            ]);
            $this->fail('新規パスワード桁数不足時のバリデーションエラーが発生しませんでした。');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('password', $e->errors());
        }
    }
}
