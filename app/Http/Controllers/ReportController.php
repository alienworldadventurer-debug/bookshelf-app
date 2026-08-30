<?php

namespace App\Http\Controllers;

use App\Models\ReadingPlan;
use App\Models\Review;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * マイ読書レポート画面の表示
     */
    public function index(): View
    {
        $user = auth()->user();

        // 1. ログインユーザーの全レビューを、紐づく書籍・ジャンル情報ごと一括取得 (Eager Loading)
        $reviews = Review::with('book.genres')
            ->where('user_id', $user->id)
            ->get();

        // ====== 基本サマリーの集計（Collection活用） ======
        // 総レビュー数
        $totalReviews = $reviews->count();

        // 読了冊数（ユニーク書籍数）
        $booksRead = $reviews->pluck('book_id')->unique()->count();

        // 平均評価（レビューがない場合は 0.0 とする）
        $averageRating = $reviews->isEmpty() ? 0.0 : round($reviews->avg('rating'), 1);
        // ========================================================

        $stats = [
            'summary' => [
                'total_reviews' => $totalReviews,
                'books_read' => $booksRead,
                'average_rating' => $averageRating,
            ],
            'rating_distribution' => collect([]), // 後のタスクで実装
            'top_rated_books' => [],              // 後のタスクで実装
            'genre_ratings' => [],                // 後のタスクで実装
        ];

        return view('reports.index', compact('stats'));

        // 2. 読了済み（completed）の読書計画をサマリー用に一括取得
        $completedPlans = ReadingPlan::where('user_id', $user->id)
            ->where('status', 'completed')
            ->get();

        // ※ 次のタスクで、ここで取得したデータを使ってコレクション集計を行います
        $stats = [
            'summary' => [
                'total_reviews' => $reviews->count(),
                'books_read' => $completedPlans->count(),
                'average_rating' => 0.0, // 後ほど集計
            ],
        ];

        return view('reports.index', compact('stats'));
    }
}
