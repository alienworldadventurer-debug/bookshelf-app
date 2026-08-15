<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    /**
     * お気に入り一覧画面の表示 (index)
     */
    public function index(): View
    {
        // ログイン中のユーザーがお気に入りに登録している書籍を10件/ページでページネーション取得します
        $books = auth()->user()->favoriteBooks()->paginate(10);

        // データをBlade（PG10 お気に入り一覧）に渡して表示を返します
        return view('favorites.index', ['books' => $books]);
    }

    /**
     * お気に入り登録・解除のトグル処理 (store)
     */
    public function store(Book $book): RedirectResponse
    {
        // ログイン中のユーザーのお気に入り書籍リレーションに対して、対象書籍IDをトグルします
        auth()->user()->favoriteBooks()->toggle($book->id);

        // トグル操作完了後、直前の画面にリダイレクトします
        return back();
    }
}
