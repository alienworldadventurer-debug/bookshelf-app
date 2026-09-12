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
     * ジャンル一覧画面を表示する。
     *
     * @return View ジャンル一覧画面
     */
    public function index(): View
    {
        $genres = Genre::withCount('books')->get();

        return view('genres.index', ['genres' => $genres]);
    }

    /**
     * ジャンル登録画面を表示する。
     *
     * @return View ジャンル登録画面
     */
    public function create(): View
    {
        return view('genres.create');
    }

    /**
     * ジャンルを新規登録する。
     *
     * @param  StoreGenreRequest  $request  登録するジャンル情報
     * @return RedirectResponse ジャンル一覧画面へのリダイレクト
     */
    public function store(StoreGenreRequest $request): RedirectResponse
    {
        Genre::create($request->validated());

        return redirect()->route('genres.index')
            ->with('success', 'ジャンルを登録しました。');
    }

    /**
     * ジャンルに紐づく書籍一覧を表示する。
     *
     * @param  Genre  $genre  表示対象のジャンル
     * @return View ジャンル詳細画面
     */
    public function show(Genre $genre): View
    {
        $books = $genre->books()->paginate(10);

        return view('genres.show', [
            'genre' => $genre,
            'books' => $books,
        ]);
    }

    /**
     * ジャンル編集画面を表示する。
     *
     * @param  Genre  $genre  編集対象のジャンル
     * @return View ジャンル編集画面
     */
    public function edit(Genre $genre): View
    {
        return view('genres.edit', ['genre' => $genre]);
    }

    /**
     * ジャンルを更新する。
     *
     * @param  UpdateGenreRequest  $request  更新するジャンル情報
     * @param  Genre  $genre  更新対象のジャンル
     * @return RedirectResponse ジャンル一覧画面へのリダイレクト
     */
    public function update(UpdateGenreRequest $request, Genre $genre): RedirectResponse
    {
        $genre->update($request->validated());

        return redirect()->route('genres.index')
            ->with('success', 'ジャンル名を更新しました。');
    }

    /**
     * 書籍に紐づいていないジャンルを削除する。
     *
     * @param  Genre  $genre  削除対象のジャンル
     * @return RedirectResponse ジャンル一覧画面へのリダイレクト
     */
    public function destroy(Genre $genre): RedirectResponse
    {
        if ($genre->books()->exists()) {
            return redirect()->route('genres.index')
                ->with('error', 'このジャンルには書籍が紐付いているため削除できません。');
        }

        $genre->delete();

        return redirect()->route('genres.index')
            ->with('success', 'ジャンルを削除しました。');
    }
}
