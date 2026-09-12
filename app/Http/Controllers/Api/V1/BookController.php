<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexBookRequest;
use App\Http\Requests\Api\V1\StoreBookRequest;
use App\Http\Requests\Api\V1\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    /**
     * 書籍一覧を検索・絞り込みし、ページネーション付きで返す。
     *
     * @param  IndexBookRequest  $request  検索条件とページネーション条件
     * @return AnonymousResourceCollection 書籍のリソースコレクション
     */
    public function index(IndexBookRequest $request): AnonymousResourceCollection
    {
        $query = Book::query();

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->where(function (Builder $q) use ($keyword): void {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('author', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('genre')) {
            $genreId = $request->genre;
            $query->whereHas('genres', function (Builder $q) use ($genreId): void {
                $q->where('genres.id', $genreId);
            });
        }

        $perPage = (int) $request->input('per_page', 20);

        $books = $query->with(['genres'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return BookResource::collection($books);
    }

    /**
     * 書籍詳細を関連データとともに返す。
     *
     * @param  Book  $book  表示対象の書籍
     * @return BookResource 書籍リソース
     */
    public function show(Book $book): BookResource
    {
        $book->load(['genres', 'reviews.user'])
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');

        return new BookResource($book);
    }

    /**
     * 新規書籍とジャンルの関連を登録する。
     *
     * @param  StoreBookRequest  $request  登録する書籍情報
     * @return JsonResponse 作成した書籍リソース
     */
    public function store(StoreBookRequest $request): JsonResponse
    {
        $book = DB::transaction(function () use ($request): Book {
            $data = $request->safe()->except('genres');
            $data['user_id'] = $request->user()->id;
            $book = Book::create($data);
            $book->genres()->attach($request->genres);

            return $book;
        });

        $book->load(['genres'])
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');

        return (new BookResource($book))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 書籍情報とジャンルの関連を更新する。
     *
     * @param  UpdateBookRequest  $request  更新する書籍情報
     * @param  Book  $book  更新対象の書籍
     * @return BookResource 更新後の書籍リソース
     */
    public function update(UpdateBookRequest $request, Book $book): BookResource
    {
        $this->authorize('update', $book);

        $book = DB::transaction(function () use ($request, $book): Book {
            $book->update($request->safe()->except('genres'));
            $book->genres()->sync($request->genres);

            return $book;
        });

        $book->load(['genres'])
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');

        return new BookResource($book);
    }

    /**
     * 書籍を削除する。
     *
     * @param  Book  $book  削除対象の書籍
     * @return JsonResponse 削除結果
     */
    public function destroy(Book $book): JsonResponse
    {
        $this->authorize('delete', $book);
        $book->delete();

        return response()->json([
            'message' => '書籍を削除しました。',
        ], 200);
    }
}
