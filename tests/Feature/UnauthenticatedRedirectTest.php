<?php

namespace Tests\Feature;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnauthenticatedRedirectTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ゲストユーザーが認証必須画面へアクセスするとログイン画面へリダイレクトされることを検証する。
     *
     * @return void 各保護対象ルートのリダイレクト先を確認する
     */
    public function test_guest_is_redirected_to_login_on_protected_routes(): void
    {
        $responseBookCreate = $this->get(route('books.create'));
        $responseBookCreate->assertRedirect(route('login'));

        $responseGenreIndex = $this->get(route('genres.index'));
        $responseGenreIndex->assertRedirect(route('login'));

        $responseFavoritesIndex = $this->get(route('favorites.index'));
        $responseFavoritesIndex->assertRedirect(route('login'));

        $book = Book::factory()->create();
        $responseBookEdit = $this->get(route('books.edit', $book));
        $responseBookEdit->assertRedirect(route('login'));

        $responseReportsIndex = $this->get(route('reports.index'));
        $responseReportsIndex->assertRedirect(route('login'));

        $responseReadingPlansIndex = $this->get(route('reading-plans.index'));
        $responseReadingPlansIndex->assertRedirect(route('login'));

        $responseNotificationsIndex = $this->get(route('notifications.index'));
        $responseNotificationsIndex->assertRedirect(route('login'));
    }
}
