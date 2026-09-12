<?php

namespace Tests\Feature\Models;

use App\Models\Book;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ジャンルが複数の書籍に関連付けられることを検証する。
     *
     * @return void 登録した2件の書籍をジャンルから取得できることを確認する
     */
    public function test_genre_belongs_to_many_books(): void
    {
        $genre = Genre::factory()->create();
        $books = Book::factory()->count(2)->create();

        $genre->books()->attach($books->pluck('id')->toArray());

        $this->assertCount(2, $genre->books);
        $this->assertTrue($genre->books->contains($books->first()));
    }
}
