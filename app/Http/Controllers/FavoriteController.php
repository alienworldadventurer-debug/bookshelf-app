<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    /**
     * ログインユーザーのお気に入り書籍一覧を表示する。
     *
     * @return View お気に入り一覧画面
     */
    public function index(): View
    {
        $books = auth()->user()->favoriteBooks()->paginate(10);

        return view('favorites.index', ['books' => $books]);
    }

    /**
     * 指定した書籍のお気に入り登録状態を切り替える。
     *
     * @param  Book  $book  お気に入り登録状態を切り替える書籍
     * @return RedirectResponse 直前の画面へのリダイレクト
     */
    public function store(Book $book): RedirectResponse
    {
        auth()->user()->favoriteBooks()->toggle($book->id);

        return back();
    }
}
