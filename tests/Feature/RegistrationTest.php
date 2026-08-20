<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertStatus(200);
    }

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

    public function test_password_must_be_at_least_8_characters(): void
    {
        // 境界値テスト（7文字はNG、8文字はOK）
        // ① 7文字（NG）の検証
        $responseNg = $this->post(route('register'), [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'pass123', // 7文字
            'password_confirmation' => 'pass123',
        ]);
        $responseNg->assertSessionHasErrors(['password']);

        // ② 8文字（OK）の検証
        $responseOk = $this->post(route('register'), [
            'name' => 'テストユーザー2',
            'email' => 'test2@example.com',
            'password' => 'pass1234', // 8文字
            'password_confirmation' => 'pass1234',
        ]);
        $responseOk->assertRedirect(route('books.index'));
        $this->assertAuthenticated();
    }

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
