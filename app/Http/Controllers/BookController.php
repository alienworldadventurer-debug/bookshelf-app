<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
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
        $books = $query->paginate(10)->appends(request()->query());

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

    /**
     * ISBNコードからGoogle Books APIを利用して書籍情報を検索・返却する
     *
     * @return JsonResponse
     */
    public function searchByIsbn(string $isbn)
    {
        // 1. バリデーション（13桁の数値であることを検証）
        $validator = Validator::make(
            ['isbn' => $isbn],
            ['isbn' => ['required', 'string', 'digits:13']]
        );

        if ($validator->fails()) {
            return response()->json([
                'error' => 'ISBNは13桁の半角数字で入力してください。',
            ], 422);
        }

        try {
            // 2. Google Books API 呼び出しの準備
            $apiUrl = config('services.google_books.url', 'https://www.googleapis.com/books/v1/volumes');
            $apiKey = config('services.google_books.key');

            $queryParams = [
                'q' => 'isbn:'.$isbn,
            ];
            if ($apiKey) {
                $queryParams['key'] = $apiKey;
            }

            // 3. APIへのリクエスト送信（Httpファサードの使用）
            $response = Http::get($apiUrl, $queryParams);

            // 4. API側で障害（500や503など）が起きている場合のエラーハンドリング
            if (! $response->successful()) {
                return response()->json([
                    'error' => '書籍情報の取得に失敗しました。時間をおいて再度お試しいただくか、手動で入力してください。',
                ], 500);
            }

            $data = $response->json();
            $totalItems = $data['totalItems'] ?? 0;

            // 5. 該当書籍が見つからなかった場合（404エラーハンドリング）
            if ($totalItems === 0 || ! isset($data['items'][0]['volumeInfo'])) {
                return response()->json([
                    'error' => '書籍情報が見つかりませんでした。',
                ], 404);
            }

            // 6. 書籍情報の抽出
            $volumeInfo = $data['items'][0]['volumeInfo'];

            $title = $volumeInfo['title'] ?? '';
            $authors = $volumeInfo['authors'] ?? [];
            $authorString = is_array($authors) ? implode(', ', $authors) : ''; // 著者が複数いる場合はカンマで連結
            $description = $volumeInfo['description'] ?? '';

            // 画像URL（サムネイルがあれば取得し、セキュリティエラー回避のため https に置換）
            $imageUrl = $volumeInfo['imageLinks']['thumbnail'] ?? '';
            if (str_starts_with($imageUrl, 'http://')) {
                $imageUrl = str_replace('http://', 'https://', $imageUrl);
            }

            // 出版日
            $publishedDate = $volumeInfo['publishedDate'] ?? null;

            // 7. 正常系：書籍データをJSONで返却
            return response()->json([
                'title' => $title,
                'author' => $authorString,
                'description' => $description,
                'image_url' => $imageUrl,
                'published_date' => $publishedDate,
            ]);

        } catch (Exception $e) {
            // 通信タイムアウトや想定外の例外が発生した場合（通信エラー時のハンドリング）
            return response()->json([
                'error' => '書籍情報の取得に失敗しました。時間をおいて再度お試しいただくか、手動で入力してください。',
            ], 500);
        }
    }
}
