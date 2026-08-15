<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGenreRequest;
use App\Http\Requests\UpdateGenreRequest;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GenreController extends Controller
{
    /**
     * ジャンル一覧画面の表示 (index)
     */
    public function index(): View
    {
        // 各ジャンルに紐づく書籍数を自動カウントして取得します
        $genres = Genre::withCount('books')->get();

        return view('genres.index', ['genres' => $genres]);
    }

    /**
     * ジャンル登録画面の表示 (create)
     */
    public function create(): View
    {
        return view('genres.create');
    }

    /**
     * ジャンルの新規登録処理 (store)
     */
    public function store(StoreGenreRequest $request): RedirectResponse
    {
        // バリデーション済みのデータで新規登録
        Genre::create($request->validated());

        return redirect()->route('genres.index')
            ->with('success', 'ジャンルを登録しました。');
    }

    /**
     * ジャンル詳細画面（ジャンル別書籍一覧）の表示 (show)
     */
    public function show(Genre $genre): View
    {
        // 選択されたジャンルに紐づく書籍を10件/ページでページネーション取得します
        $books = $genre->books()->paginate(10);

        return view('genres.show', [
            'genre' => $genre,
            'books' => $books,
        ]);
    }

    /**
     * ジャンル編集画面の表示 (edit)
     */
    public function edit(Genre $genre): View
    {
        return view('genres.edit', ['genre' => $genre]);
    }

    /**
     * ジャンルの更新処理 (update)
     */
    public function update(UpdateGenreRequest $request, Genre $genre): RedirectResponse
    {
        // バリデーション済みのデータで更新
        $genre->update($request->validated());

        return redirect()->route('genres.index')
            ->with('success', 'ジャンル名を更新しました。');
    }

    /**
     * ジャンルの削除処理 (destroy)
     */
    public function destroy(Genre $genre): RedirectResponse
    {
        // 【ビジネスルール】書籍が1冊でも紐づいているジャンルは削除を拒否する
        if ($genre->books()->exists()) {
            return redirect()->route('genres.index')
                ->with('error', 'このジャンルには書籍が紐付いているため削除できません。');
        }

        // 紐づく書籍がない場合のみ物理削除を実行
        $genre->delete();

        return redirect()->route('genres.index')
            ->with('success', 'ジャンルを削除しました。');
    }
}