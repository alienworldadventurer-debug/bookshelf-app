<?php

namespace Tests\Feature\Models;

use App\Models\Book;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreTest extends TestCase
{
    use RefreshDatabase;

    public function test_genre_belongs_to_many_books(): void
    {
        $genre = Genre::factory()->create();
        $books = Book::factory()->count(2)->create();

        $genre->books()->attach($books->pluck('id')->toArray());

        $this->assertCount(2, $genre->books);
        $this->assertTrue($genre->books->contains($books->first()));
    }
}
