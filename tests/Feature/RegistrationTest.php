<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 登録画面が正常に表示されることを検証する。
     *
     * @return void 登録画面がHTTP 200を返すことを確認する
     */
    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertStatus(200);
    }

    /**
     * 有効な情報を入力した新規ユーザーが登録され、ログイン状態になることを検証する。
     *
     * @return void ユーザー保存、リダイレクト、認証状態を確認する
     */
    public function test_new_users_can_register(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('books.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
        $this->assertAuthenticated();
    }

    /**
     * ユーザー名とメールアドレスが未入力の場合、登録が拒否されることを検証する。
     *
     * @return void 必須項目のバリデーションエラーと未認証状態を確認する
     */
    public function test_name_and_email_are_required(): void
    {
        $response = $this->post(route('register'), [
            'name' => '',
            'email' => '',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['name', 'email']);
        $this->assertGuest();
    }

    /**
     * 既に登録済みのメールアドレスでは新規登録できないことを検証する。
     *
     * @return void メールアドレスの重複エラーと未認証状態を確認する
     */
    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->post(route('register'), [
            'name' => 'テストユーザー',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    /**
     * パスワードが8文字未満では拒否され、8文字以上では登録できることを検証する。
     *
     * @return void パスワード長の境界値に対する結果を確認する
     */
    public function test_password_must_be_at_least_8_characters(): void
    {
        $responseNg = $this->post(route('register'), [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'pass123',
            'password_confirmation' => 'pass123',
        ]);
        $responseNg->assertSessionHasErrors(['password']);

        $responseOk = $this->post(route('register'), [
            'name' => 'テストユーザー2',
            'email' => 'test2@example.com',
            'password' => 'pass1234',
            'password_confirmation' => 'pass1234',
        ]);
        $responseOk->assertRedirect(route('books.index'));
        $this->assertAuthenticated();
    }

    /**
     * パスワードと確認用パスワードが一致しない場合、登録が拒否されることを検証する。
     *
     * @return void パスワード確認のバリデーションエラーと未認証状態を確認する
     */
    public function test_password_confirmation_must_match(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different123',
        ]);

        $response->assertSessionHasErrors(['password']);
        $this->assertGuest();
    }
}
