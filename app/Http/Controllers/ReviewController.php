<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReviewController extends Controller
{
    /**
     * 新規レビューの保存 (store)
     */
    public function store(StoreReviewRequest $request, Book $book): RedirectResponse
    {
        // ログイン中のユーザーIDと書籍IDを組み合わせてレビューを作成・保存します
        Review::create([
            'user_id' => auth()->id(),
            'book_id' => $book->id,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        // 保存後、「レビューを投稿しました。」というフラッシュメッセージ付きで詳細画面へリダイレクト
        return redirect()->route('books.show', $book)
            ->with('success', 'レビューを投稿しました。');
    }

    /**
     * レビュー編集画面の表示 (edit)
     */
    public function edit(Review $review): View
    {
        // Policyの認可チェック（本人以外の編集アクセスを自動で 403 Forbidden にします）
        $this->authorize('update', $review);

        // 提供されているレビュー編集用Blade（PG09）を呼び出し、レビューデータを渡します
        return view('reviews.edit', compact('review'));
    }

    /**
     * レビューの更新処理 (update)
     */
    public function update(UpdateReviewRequest $request, Review $review): RedirectResponse
    {
        // Policyの認可チェック
        $this->authorize('update', $review);

        // バリデーション済みのデータでレビュー内容を更新します
        $review->update($request->validated());

        // 更新後、「レビューを更新しました。」というフラッシュメッセージ付きで詳細画面へ戻します
        return redirect()->route('books.show', $review->book)
            ->with('success', 'レビューを更新しました。');
    }

    /**
     * レビューの削除処理 (destroy)
     */
    public function destroy(Review $review): RedirectResponse
    {
        // Policyの認可チェック
        $this->authorize('delete', $review);

        // リダイレクト先として使用するため、削除前に所属する書籍情報を保持しておきます
        $book = $review->book;

        // レビュー本体を削除します
        // ※「review_likes（いいね）」テーブルは、テーブル仕様書(FKのカスケード設定)に基づき、
        // 　データベース側で連動して自動で安全にカスケード削除されます。
        $review->delete();

        // 削除後、「レビューを削除しました。」というフラッシュメッセージ付きで詳細画面に戻ります
        return redirect()->route('books.show', $book)
            ->with('success', 'レビューを削除しました。');
    }
}
