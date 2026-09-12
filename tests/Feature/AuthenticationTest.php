<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 未認証ユーザーがログイン画面を表示できることを検証する。
     *
     * @return void ログイン画面がHTTP 200を返すことを確認する
     */
    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
    }

    /**
     * 正しい認証情報でログインできることを検証する。
     *
     * @return void 書籍一覧へリダイレクトされ、対象ユーザーが認証済みになることを確認する
     */
    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('books.index'));
        $this->assertAuthenticatedAs($user);
    }

    /**
     * 誤ったパスワードではログインできないことを検証する。
     *
     * @return void メールアドレスのセッションエラーとゲスト状態を確認する
     */
    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest();
    }

    /**
     * 認証済みユーザーがログアウトできることを検証する。
     *
     * @return void ログイン画面へリダイレクトされ、ゲスト状態になることを確認する
     */
    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * 認証済みユーザーがゲスト向け画面へアクセスするとリダイレクトされることを検証する。
     *
     * @return void 登録画面とログイン画面から書籍一覧へ遷移することを確認する
     */
    public function test_authenticated_users_are_redirected_when_accessing_guest_routes(): void
    {
        $user = User::factory()->create();

        $responseRegister = $this->actingAs($user)->get(route('register'));
        $responseRegister->assertRedirect(route('books.index'));

        $responseLogin = $this->actingAs($user)->get(route('login'));
        $responseLogin->assertRedirect(route('books.index'));
    }
}
