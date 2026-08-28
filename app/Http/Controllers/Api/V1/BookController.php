<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexBookRequest;
use App\Http\Requests\Api\V1\StoreBookRequest;
use App\Http\Requests\Api\V1\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    /**
     * 書籍一覧を取得（検索・絞り込み・ページネーション対応）。
     */
    public function index(IndexBookRequest $request): AnonymousResourceCollection
    {
        $query = Book::query();

        // キーワード検索（タイトル、著者名の部分一致）
        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('author', 'like', "%{$keyword}%");
            });
        }

        // ジャンルでの絞り込み
        if ($request->filled('genre')) {
            $genreId = $request->genre;
            $query->whereHas('genres', function ($q) use ($genreId) {
                $q->where('genres.id', $genreId);
            });
        }

        // 1ページあたりの件数（デフォルトは10件）
        $perPage = (int) $request->input('per_page', 10);

        // N+1対策としてEager Loadingと集計クエリを実行
        $books = $query->with(['genres'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->orderBy('id', 'desc') // 最新登録順
            ->paginate($perPage);

        return BookResource::collection($books);
    }

    /**
     * 書籍詳細を取得。
     */
    public function show(Book $book): BookResource
    {
        // 詳細画面に必要なリレーションと平均評価・レビュー件数をロード
        $book->load(['genres', 'reviews.user'])
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');

        return new BookResource($book);
    }

    /**
     * 新規書籍を登録。
     */
    public function store(StoreBookRequest $request): JsonResponse
    {
        // トランザクション内で安全に書籍情報とジャンル紐付けを保存
        $book = DB::transaction(function () use ($request) {

            // 1. リクエストデータ（genres以外）を取得
            $data = $request->safe()->except('genres');

            // 2. ログイン中のユーザーIDで user_id を上書き（自動セット）
            $data['user_id'] = $request->user()->id;

            // 3. 書籍をDBに作成
            $book = Book::create($data);
            $book->genres()->attach($request->genres);

            return $book;
        });

        // レスポンス整形用にデータをロード
        $book->load(['genres'])
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');

        return (new BookResource($book))
            ->response()
            ->setStatusCode(201); // 201 Created を明示
    }

    /**
     * 書籍情報を更新。
     */
    public function update(UpdateBookRequest $request, Book $book): BookResource
    {
        // この書籍を編集する権限があるか（登録者本人か）をチェック！
        $this->authorize('update', $book);

        // トランザクション内で安全に書籍情報とジャンル紐付けを更新
        $book = DB::transaction(function () use ($request, $book) {
            $book->update($request->safe()->except('genres'));
            $book->genres()->sync($request->genres);

            return $book;
        });

        // レスポンス整形用にデータをロード
        $book->load(['genres'])
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');

        return new BookResource($book);
    }

    /**
     * 書籍を削除。
     */
    public function destroy(Book $book): JsonResponse
    {
        // 認可チェックを適用（本人以外は403 Forbiddenになる）
        $this->authorize('delete', $book);

        // データベースの cascadeOnDelete 設定に基づき、関連データも自動で安全に削除されます
        $book->delete();

        return response()->json([
            'message' => '書籍を削除しました。',
        ], 200);
    }
}
