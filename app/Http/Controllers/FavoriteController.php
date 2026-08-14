<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;

class FavoriteController extends Controller
{
    /**
     * お気に入り登録・解除のトグル処理
     */
    public function store(Book $book): RedirectResponse
    {
        // ログイン中のユーザーのお気に入り書籍リレーションに対して、対象書籍IDをトグルします
        auth()->user()->favoriteBooks()->toggle($book->id);

        // トグル操作完了後、直前の画面（一覧または詳細）にそのままリダイレクトします
        return back();
    }
}