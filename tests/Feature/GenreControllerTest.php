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

    // =========================================================================
    // 1. ジャンル一覧・詳細のテスト (正常系・ページネーション)
    // =========================================================================

    /**
     * ログインユーザーがジャンル一覧画面を表示でき、紐付く書籍数が正しく表示されていること
     */
    public function test_authenticated_user_can_view_genres_index(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => 'プログラミング']);

        // 書籍を2冊作成し、多対多の中間テーブルを介してジャンルに紐付ける
        $books = Book::factory()->count(2)->create(['user_id' => $user->id]);
        foreach ($books as $book) {
            $book->genres()->attach($genre->id);
        }

        // Act (実行)
        $response = $this->actingAs($user)->get(route('genres.index'));

        // Assert (検証)
        $response->assertStatus(200);
        $response->assertViewHas('genres');
        $response->assertSee('プログラミング');

        // ビューに渡されたジャンルデータの書籍カウントが「2」であることを確認
        $viewGenres = $response->viewData('genres');
        $this->assertEquals(2, $viewGenres->first()->books_count);
    }

    /**
     * ジャンル詳細画面で、紐付く書籍が1ページあたり最大10件でページネーション表示されること
     */
    public function test_authenticated_user_can_view_genre_show_with_pagination(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        // このジャンルに紐付く書籍を11冊作成する
        $books = Book::factory()->count(11)->create(['user_id' => $user->id]);
        foreach ($books as $book) {
            $book->genres()->attach($genre->id);
        }

        // Act (実行)
        $response = $this->actingAs($user)->get(route('genres.show', $genre));

        // Assert (検証)
        $response->assertStatus(200);
        $response->assertViewHas('books');

        // 1ページ目の表示件数が最大「10件」に制限されていることを検証
        $this->assertCount(10, $response->viewData('books'));
    }

    // =========================================================================
    // 2. ジャンル登録 (Store) のテスト (正常系・バリデーション)
    // =========================================================================

    /**
     * ログインユーザーが正常に新規ジャンルを登録できること
     */
    public function test_authenticated_user_can_store_genre(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();

        // Act (実行)
        $response = $this->actingAs($user)->post(route('genres.store'), [
            'name' => 'デザイン',
        ]);

        // Assert (検証)
        $response->assertRedirect(route('genres.index')); // 登録後は一覧へリダイレクト
        $this->assertDatabaseHas('genres', [
            'name' => 'デザイン',
        ]);
    }

    /**
     * すでに存在するジャンル名で登録しようとした場合、重複バリデーションエラーが発生すること
     */
    public function test_genre_store_validation_fails_with_duplicate_name(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        Genre::factory()->create(['name' => '小説']); // 重複テスト用

        // Act (実行)
        $response = $this->actingAs($user)->post(route('genres.store'), [
            'name' => '小説',
        ]);

        // Assert (検証)
        $response->assertSessionHasErrors(['name']);
    }

    // =========================================================================
    // 3. ジャンル更新 (Update) のテスト (正常系・バリデーション)
    // =========================================================================

    /**
     * ログインユーザーがジャンル名を正常に更新できること
     */
    public function test_authenticated_user_can_update_genre(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => '旧ジャンル名']);

        // Act (実行)
        $response = $this->actingAs($user)->put(route('genres.update', $genre), [
            'name' => '新ジャンル名',
        ]);

        // Assert (検証)
        $response->assertRedirect(route('genres.index'));
        $this->assertDatabaseHas('genres', [
            'id' => $genre->id,
            'name' => '新ジャンル名',
        ]);
    }

    /**
     * ジャンル更新時、自分の現在のジャンル名であれば重複エラーにならずに保存できること（Rule::ignoreの検証）
     */
    public function test_genre_update_ignores_own_name(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $genre = Genre::factory()->create(['name' => '小説']);

        // Act (実行) - 名前を変更せずにそのまま送信
        $response = $this->actingAs($user)->put(route('genres.update', $genre), [
            'name' => '小説',
        ]);

        // Assert (検証) - エラーにならずにリダイレクトされること
        $response->assertRedirect(route('genres.index'));
        $response->assertSessionHasNoErrors();
    }

    /**
     * ジャンル更新時、他者が使っているジャンル名と重複した場合はバリデーションエラーが発生すること
     */
    public function test_genre_update_validation_fails_with_other_duplicate_name(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        Genre::factory()->create(['name' => '小説']);
        $genre = Genre::factory()->create(['name' => 'ビジネス']);

        // Act (実行) - 「ビジネス」を他人が使っている「小説」に変えようとする
        $response = $this->actingAs($user)->put(route('genres.update', $genre), [
            'name' => '小説',
        ]);

        // Assert (検証)
        $response->assertSessionHasErrors(['name']);
    }

    // =========================================================================
    // 4. ジャンル削除 (Destroy) のテスト (ビジネスルール)
    // =========================================================================

    /**
     * 書籍が1冊も紐付いていないジャンルは正常に削除できること
     */
    public function test_authenticated_user_can_destroy_unused_genre(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        // Act (実行)
        $response = $this->actingAs($user)->delete(route('genres.destroy', $genre));

        // Assert (検証)
        $response->assertRedirect(route('genres.index'));
        $this->assertDatabaseMissing('genres', ['id' => $genre->id]);
    }

    /**
     * 書籍が1冊でも紐付いているジャンルは削除できず、エラーメッセージと共にリダイレクトバックされること
     */
    public function test_authenticated_user_cannot_destroy_genre_with_books(): void
    {
        // Arrange (準備)
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        // 書籍を作成し、中間テーブルでジャンルと紐付ける
        $book = Book::factory()->create(['user_id' => $user->id]);
        $book->genres()->attach($genre->id);

        // Act (実行)
        $response = $this->actingAs($user)->delete(route('genres.destroy', $genre));

        // Assert (検証)
        $response->assertRedirect(route('genres.index'));

        // セッションのエラーメッセージを確認
        $response->assertSessionHas('error', 'このジャンルには書籍が紐付いているため削除できません。');

        // データベースにジャンルが消えずに残っていることを確認
        $this->assertDatabaseHas('genres', ['id' => $genre->id]);
    }
}
