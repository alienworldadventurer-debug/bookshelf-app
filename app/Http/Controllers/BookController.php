<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Throwable;

class BookController extends Controller
{
    /**
     * 書籍一覧画面を表示する。
     *
     * @param  Request  $request  検索、絞り込み、ソート条件を含むリクエスト
     * @return View 書籍一覧画面
     */
    public function index(Request $request): View
    {
        $query = Book::with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $query->where(function (Builder $q) use ($keyword): void {
                $q->where('title', 'like', '%'.$keyword.'%')
                    ->orWhere('author', 'like', '%'.$keyword.'%');
            });
        }

        if ($request->filled('genre')) {
            $genreId = $request->input('genre');
            $query->whereHas('genres', function (Builder $q) use ($genreId): void {
                $q->where('genres.id', $genreId);
            });
        }

        $sort = $request->input('sort', 'newest');
        switch ($sort) {
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'title':
                $query->orderBy('title', 'asc');
                break;
            case 'rating':
                $query->orderByRaw('reviews_avg_rating IS NULL ASC')
                    ->orderBy('reviews_avg_rating', 'desc')
                    ->orderBy('created_at', 'desc');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $books = $query->paginate(10)->appends(request()->query());
        $genres = Genre::orderBy('name', 'asc')->get();

        return view('books.index', compact('books', 'genres'));
    }

    /**
     * 書籍登録画面を表示する。
     *
     * @return View 書籍登録画面
     */
    public function create(): View
    {
        $genres = Genre::all();

        return view('books.create', compact('genres'));
    }

    /**
     * 新規書籍を保存する。
     *
     * @param  StoreBookRequest  $request  書籍情報を含むリクエスト
     * @return RedirectResponse 書籍一覧画面へのリダイレクト
     */
    public function store(StoreBookRequest $request): RedirectResponse
    {
        $book = auth()->user()->books()->create($request->validated());
        $book->genres()->attach($request->genres);

        return redirect()
            ->route('books.index')
            ->with('success', '書籍を登録しました。');
    }

    /**
     * 書籍詳細画面を表示する。
     *
     * @param  Book  $book  表示対象の書籍
     * @return View 書籍詳細画面
     */
    public function show(Book $book): View
    {
        $book->load(['genres', 'reviews.user']);
        $book->loadAvg('reviews', 'rating');

        return view('books.show', compact('book'));
    }

    /**
     * 書籍編集画面を表示する。
     *
     * @param  Book  $book  編集対象の書籍
     * @return View 書籍編集画面
     */
    public function edit(Book $book): View
    {
        $this->authorize('update', $book);

        $genres = Genre::all();

        return view('books.edit', compact('book', 'genres'));
    }

    /**
     * 書籍情報を更新する。
     *
     * @param  UpdateBookRequest  $request  更新内容を含むリクエスト
     * @param  Book  $book  更新対象の書籍
     * @return RedirectResponse 書籍詳細画面へのリダイレクト
     */
    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book);
        $book->update($request->validated());
        $book->genres()->sync($request->genres);

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍情報を更新しました。');
    }

    /**
     * 書籍を削除する。
     *
     * @param  Book  $book  削除対象の書籍
     * @return RedirectResponse 書籍一覧画面へのリダイレクト
     */
    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);
        $book->delete();

        return redirect()
            ->route('books.index')
            ->with('success', '書籍を削除しました。');
    }

    /**
     * ISBNコードからGoogle Books APIを利用して書籍情報を検索・返却する。
     *
     * @param  Request  $request  ISBNコードを含むリクエスト
     * @param  string  $isbn  ルートパラメータのISBNコード
     * @return JsonResponse 書籍情報またはエラーメッセージ
     */
    public function searchByIsbn(Request $request, string $isbn): JsonResponse
    {
        $inputIsbn = $request->input('isbn', $isbn);

        $validator = Validator::make(
            ['isbn' => $inputIsbn],
            ['isbn' => ['required', 'bail', 'string', 'digits:13']],
            [
                'isbn.required' => 'ISBNコードは必須です。',
                'isbn.string' => 'ISBNは正しい形式で入力してください。',
                'isbn.digits' => 'ISBNは13桁の半角数字で入力してください。',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first('isbn'),
            ], 422);
        }

        try {
            $apiUrl = config('services.google_books.url', 'https://www.googleapis.com/books/v1/volumes');
            $apiKey = config('services.google_books.key');

            $queryParams = [
                'q' => 'isbn:'.$inputIsbn,
            ];
            if ($apiKey) {
                $queryParams['key'] = $apiKey;
            }

            $response = Http::withoutVerifying()->get($apiUrl, $queryParams);

            if (! $response->successful()) {
                Log::error('Google API HTTP Error: '.$response->status().' - '.$response->body());

                return response()->json([
                    'error' => '書籍情報の取得に失敗しました。時間をおいて再度お試しいただくか、手動で入力してください。',
                ], 500);
            }

            $data = $response->json();
            $totalItems = $data['totalItems'] ?? 0;

            if ($totalItems === 0 || ! isset($data['items'][0]['volumeInfo'])) {
                return response()->json([
                    'error' => '書籍情報が見つかりませんでした。',
                ], 404);
            }

            $volumeInfo = $data['items'][0]['volumeInfo'];

            $title = $volumeInfo['title'] ?? '';
            $authors = $volumeInfo['authors'] ?? [];
            $authorString = is_array($authors) ? implode(', ', $authors) : '';
            $description = $volumeInfo['description'] ?? '';

            $imageUrl = $volumeInfo['imageLinks']['thumbnail'] ?? '';
            if (str_starts_with($imageUrl, 'http://')) {
                $imageUrl = str_replace('http://', 'https://', $imageUrl);
            }

            $publishedDate = $volumeInfo['publishedDate'] ?? null;

            return response()->json([
                'title' => $title,
                'author' => $authorString,
                'description' => $description,
                'image_url' => $imageUrl,
                'published_date' => $publishedDate,
            ]);
        } catch (Throwable $e) {
            Log::error('ISBN Search Exception: '.$e->getMessage());

            return response()->json([
                'error' => '書籍情報の取得に失敗しました。時間をおいて再度お試しいただくか、手動で入力してください。',
            ], 500);
        }
    }
}
