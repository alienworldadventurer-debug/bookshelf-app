<?php

namespace Tests\Feature;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnauthenticatedRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_on_protected_routes(): void
    {
        // ① 書籍登録画面 (/books/create)
        $responseBookCreate = $this->get(route('books.create'));
        $responseBookCreate->assertRedirect(route('login'));

        // ② ジャンル管理画面 (/genres)
        $responseGenreIndex = $this->get(route('genres.index'));
        $responseGenreIndex->assertRedirect(route('login'));

        // ③ お気に入り一覧画面 (/favorites)
        $responseFavoritesIndex = $this->get(route('favorites.index'));
        $responseFavoritesIndex->assertRedirect(route('login'));

        // ④ 書籍編集画面 (/books/{book}/edit)
        $book = Book::factory()->create();
        $responseBookEdit = $this->get(route('books.edit', $book));
        $responseBookEdit->assertRedirect(route('login'));

        // ⑤ マイレポート画面 (/reports)
        $responseReportsIndex = $this->get(route('reports.index'));
        $responseReportsIndex->assertRedirect(route('login'));

        // ⑥ 読書計画一覧画面 (/reading-plans)
        $responseReadingPlansIndex = $this->get(route('reading-plans.index'));
        $responseReadingPlansIndex->assertRedirect(route('login'));

        // ⑦ 通知一覧画面 (/notifications)
        $responseNotificationsIndex = $this->get(route('notifications.index'));
        $responseNotificationsIndex->assertRedirect(route('login'));
    }
}
