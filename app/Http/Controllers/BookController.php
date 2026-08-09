<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;

class BookController extends Controller
{
    /**
     * 書籍一覧画面の表示
     */
    public function index()
    {
        // genresをEager LoadingしてN+1問題を防止。また、平均評価を効率的に取得
        $books = Book::with('genres')
            ->withAvg('reviews', 'rating')
            ->latest()
            ->paginate(10);

        return view('books.index', compact('books'));
    }

    /**
     * 書籍登録画面の表示
     */
    public function create()
    {
        // フォーム内で選択可能なジャンルマスタを全件取得して渡す
        $genres = Genre::all();

        return view('books.create', compact('genres'));
    }

    /**
     * 新規書籍のDB保存処理
     */
    public function store(StoreBookRequest $request)
    {
        // ログイン中のユーザーに紐づけて書籍レコードを作成（user_idを自動注入）
        $book = auth()->user()->books()->create($request->validated());

        // 中間テーブル（book_genre）にジャンル紐付けを登録
        $book->genres()->attach($request->genres);

        return redirect()
            ->route('books.index')
            ->with('success', '書籍を登録しました。');
    }

    /**
     * 書籍詳細画面の表示
     */
    public function show(Book $book)
    {
        // 詳細ビュー内でループ表示されるレビューの投稿者（user）のN+1問題を解決
        $book->load(['genres', 'reviews.user']);

        // 平均評価の計算ロジック（reviews_avg_rating）を正確に動的ロード
        $book->loadAvg('reviews', 'rating');

        return view('books.show', compact('book'));
    }

    /**
     * 書籍編集画面の表示
     */
    public function edit(Book $book)
    {
        // 所有者チェック（Policy適用）：本人以外は403 Forbidden
        $this->authorize('update', $book);

        $genres = Genre::all();

        return view('books.edit', compact('book', 'genres'));
    }

    /**
     * 書籍情報の更新処理
     */
    public function update(UpdateBookRequest $request, Book $book)
    {
        // 所有者チェック（Policy適用）
        $this->authorize('update', $book);

        // 書籍基本情報の更新
        $book->update($request->validated());

        // 中間テーブル（book_genre）のジャンル紐付けを完全に同期（不要なものは削除され追加分のみ登録）
        $book->genres()->sync($request->genres);

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍情報を更新しました。');
    }

    /**
     * 書籍データの削除処理
     */
    public function destroy(Book $book)
    {
        // 所有者チェック（Policy適用）
        $this->authorize('delete', $book);

        // 書籍を削除（テーブル仕様の物理削除制約により、関連レコードも自動でカスケード削除されます）
        $book->delete();

        return redirect()
            ->route('books.index')
            ->with('success', '書籍を削除しました。');
    }
}
