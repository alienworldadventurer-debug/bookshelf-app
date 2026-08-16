<?php

namespace Tests\Feature;

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
    }
}
