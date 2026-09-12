<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ログインユーザーがジャンル一覧を表示でき、各ジャンルの書籍数が正しく表示されることを検証する。
     *
     * @return void ジャンル一覧と書籍数を確認する
     */
    public function test_authenticated_user_can_view_genres_index(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => 'プログラミング']);

        $books = Book::factory()->count(2)->create(['user_id' => $user->id]);
        foreach ($books as $book) {
            $book->genres()->attach($genre->id);
        }

        $response = $this->actingAs($user)->get(route('genres.index'));

        $response->assertStatus(200);
        $response->assertViewHas('genres');
        $response->assertSee('プログラミング');

        $viewGenres = $response->viewData('genres');
        $this->assertEquals(2, $viewGenres->first()->books_count);
    }

    /**
     * ジャンル詳細画面で、関連書籍が1ページあたり最大10件で表示されることを検証する。
     *
     * @return void 1ページ目の書籍数を確認する
     */
    public function test_authenticated_user_can_view_genre_show_with_pagination(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $books = Book::factory()->count(11)->create(['user_id' => $user->id]);
        foreach ($books as $book) {
            $book->genres()->attach($genre->id);
        }

        $response = $this->actingAs($user)->get(route('genres.show', $genre));

        $response->assertStatus(200);
        $response->assertViewHas('books');
        $this->assertCount(10, $response->viewData('books'));
    }

    /**
     * ログインユーザーが新規ジャンルを登録できることを検証する。
     *
     * @return void ジャンルの保存と一覧画面へのリダイレクトを確認する
     */
    public function test_authenticated_user_can_store_genre(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('genres.store'), [
            'name' => 'デザイン',
        ]);

        $response->assertRedirect(route('genres.index'));
        $this->assertDatabaseHas('genres', ['name' => 'デザイン']);
    }

    /**
     * 既存のジャンル名で登録すると重複バリデーションエラーになることを検証する。
     *
     * @return void ジャンル名の重複エラーを確認する
     */
    public function test_genre_store_validation_fails_with_duplicate_name(): void
    {
        $user = User::factory()->create();
        Genre::factory()->create(['name' => '小説']);

        $response = $this->actingAs($user)->post(route('genres.store'), [
            'name' => '小説',
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    /**
     * ログインユーザーがジャンル名を更新できることを検証する。
     *
     * @return void ジャンルの更新と一覧画面へのリダイレクトを確認する
     */
    public function test_authenticated_user_can_update_genre(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => '旧ジャンル名']);

        $response = $this->actingAs($user)->put(route('genres.update', $genre), [
            'name' => '新ジャンル名',
        ]);

        $response->assertRedirect(route('genres.index'));
        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => '新ジャンル名',
        ]);
    }

    /**
     * 更新対象自身が現在使用しているジャンル名は重複エラーにならないことを検証する。
     *
     * @return void 現在のジャンル名で更新できることを確認する
     */
    public function test_genre_update_ignores_own_name(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => '小説']);

        $response = $this->actingAs($user)->put(route('genres.update', $genre), [
            'name' => '小説',
        ]);

        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHasNoErrors();
    }

    /**
     * 他のジャンルと同じ名前へ更新するとバリデーションエラーになることを検証する。
     *
     * @return void ジャンル名の重複エラーを確認する
     */
    public function test_genre_update_validation_fails_with_other_duplicate_name(): void
    {
        $user = User::factory()->create();
        Genre::factory()->create(['name' => '小説']);
        $genre = Genre::factory()->create(['name' => 'ビジネス']);

        $response = $this->actingAs($user)->put(route('genres.update', $genre), [
            'name' => '小説',
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    /**
     * 書籍が関連付いていないジャンルを削除できることを検証する。
     *
     * @return void ジャンルの削除と一覧画面へのリダイレクトを確認する
     */
    public function test_authenticated_user_can_destroy_unused_genre(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $response = $this->actingAs($user)->delete(route('genres.destroy', $genre));

        $response->assertRedirect(route('genres.index'));
        $this->assertDatabaseMissing('genres', ['id' => $genre->id]);
    }

    /**
     * 書籍が関連付いているジャンルは削除されず、エラーメッセージが表示されることを検証する。
     *
     * @return void 削除拒否時のリダイレクト、エラー、データ保持を確認する
     */
    public function test_authenticated_user_cannot_destroy_genre_with_books(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $book = Book::factory()->create(['user_id' => $user->id]);
        $book->genres()->attach($genre->id);

        $response = $this->actingAs($user)->delete(route('genres.destroy', $genre));

        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHas('error', 'このジャンルには書籍が紐付いているため削除できません。');
        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
    }
}
