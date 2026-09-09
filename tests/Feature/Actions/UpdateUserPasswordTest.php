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
     * @test
     * 正常系: 正しい現在のパスワードと有効な新しいパスワード（8文字以上かつ確認用と一致）を
     * 指定して実行した際、ユーザーのパスワードが正常に更新されること。
     */
    public function test_update_user_password_action_updates_password_successfully(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'), // 現在のパスワードを設定
        ]);

        $action = new UpdateUserPassword;

        $this->actingAs($user);

        // 正常系：パスワード更新の実行
        $action->update($user, [
            'current_password' => 'current-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        // パスワードが新しいものに書き換わり、ハッシュが一致することを検証
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    /**
     * @test
     * 異常系: 現在のパスワードが誤っている場合、または新規パスワードのバリデーションに違反した場合に
     * バリデーションエラー（ValidationException）が発生すること。
     */
    public function test_update_user_password_action_fails_validation(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $action = new UpdateUserPassword;

        // 異常系1: 現在のパスワードが間違っている場合
        try {
            $action->update($user, [
                'current_password' => 'wrong-password', // 間違ったパスワードを指定
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);
            $this->fail('現在のパスワード誤り時のバリデーションエラーが発生しませんでした。');
        } catch (ValidationException $e) {
            // Fortifyの仕様上、現在のパスワードエラーは `current_password` キーに格納されます
            $this->assertArrayHasKey('current_password', $e->errors());
        }

        // 異常系2: 新規パスワードが不一致の場合
        try {
            $action->update($user, [
                'current_password' => 'correct-password',
                'password' => 'new-password',
                'password_confirmation' => 'mismatch-password', // あえて一致させない
            ]);
            $this->fail('新規パスワード不一致時のバリデーションエラーが発生しませんでした。');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('password', $e->errors());
        }

        // 異常系3: 新規パスワードが8文字未満（桁数不足）の場合
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
