<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookController extends Controller
{
    /**
     * 書籍一覧画面の表示
     */
    public function index(Request $request): View // 👈 引数に Request を追加
    {
        // 1. genresをEager LoadingしてN+1問題を防止し、平均評価とレビュー件数も効率的に取得
        $query = Book::with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        // 2. キーワード検索（部分一致：title または author）
        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            // where と orWhere の論理グループ化（括弧で囲む）
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', '%'.$keyword.'%')
                    ->orWhere('author', 'like', '%'.$keyword.'%');
            });
        }

        // 3. ジャンルフィルタでの絞り込み
        if ($request->filled('genre')) {
            $genreId = $request->input('genre');
            $query->whereHas('genres', function ($q) use ($genreId) {
                $q->where('genres.id', $genreId);
            });
        }

        // 4. ソート機能の適用
        $sort = $request->input('sort', 'newest'); // 初期値として第2引数にnewestを指定
        switch ($sort) {
            case 'oldest': // 登録日が古い順
                $query->orderBy('created_at', 'asc');
                break;
            case 'title': // タイトル昇順
                $query->orderBy('title', 'asc');
                break;
            case 'rating': // 平均評価の高い順（評価がないものは最後に表示）
                $query->orderByRaw('reviews_avg_rating IS NULL ASC')
                    ->orderBy('reviews_avg_rating', 'desc')
                    ->orderBy('created_at', 'desc'); // 同スコア時は新しい順
                break;
            case 'newest': // 登録日が新しい順（デフォルト）
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        // 5. 10件ずつのページネーション＆検索クエリ文字列の維持
        $books = $query->paginate(10)->withQueryString();

        // 6. 検索フォームのプルダウン用に、全ジャンルを名前順で取得
        $genres = Genre::orderBy('name', 'asc')->get();

        // ビューに books と genres を渡す
        return view('books.index', compact('books', 'genres'));
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
