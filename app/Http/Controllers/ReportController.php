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

        // 1. ログインユーザーの全レビューを関連書籍・ジャンル込みで一括取得 (Eager Loading)
        $reviews = Review::with('book.genres')
            ->where('user_id', $user->id)
            ->get();

        // 2. ログインユーザーの完了済み（completed）読書計画を一括取得
        $completedPlans = ReadingPlan::where('user_id', $user->id)
            ->where('status', 'completed')
            ->get();

        // ====== 【タスク3】基本サマリーの集計（Collection活用） ======

        // 総レビュー数
        $totalReviews = $reviews->count();

        // 読了冊数（読書計画テーブルでステータスが completed のユニーク書籍数）
        // ※ 機能要件シート7の「PG14」定義に則り、完了した読書計画から重複のない書籍数を算出します
        $booksRead = $completedPlans->pluck('book_id')->unique()->count();

        // 平均評価（レビューがない場合は 0.0 とする）
        $averageRating = $reviews->isEmpty() ? 0.0 : round($reviews->avg('rating'), 1);

        // ====== 【タスク4】評価分布の集計（Collection活用） ======

        // 1. 実際のレビューを集計（この時点のキーは評価値そのままの 1〜5）
        $actualDistribution = $reviews->groupBy('rating')->map(fn ($group) => $group->count());

        // 2. Blade側の「$index + 1」に対応するため、キーを「マイナス1」して 0〜4 に変換する
        $mappedDistribution = $actualDistribution->mapWithKeys(function ($count, $rating) {
            return [$rating - 1 => $count];
        });

        // 3. 土台（星1=キー0 〜 星5=キー4）を用意し、実際の集計結果で置き換える（replace）
        // ※ 画面の表示順（星1が上、星5が下）に合わせるため、0から4の昇順で並べます
        $ratingDistribution = collect([0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0])
            ->replace($mappedDistribution);

        // ====== 【タスク5】高評価書籍 TOP5 の集計（Collection活用） ======
        // 1. 評価が 4 以上のレビューのみに絞り込み、評価の高い順（降順）に並べ替え、最大 5 件を抽出する
        $topRatedBooks = $reviews->filter(fn ($review) => $review->rating >= 4)
            ->sortByDesc('rating')
            ->take(5)
            // 2. Blade側のアクセス形式（連想配列）に合うように、各データを必要なキーにマッピングする
            ->map(fn ($review) => [
                'id' => $review->book->id,
                'title' => $review->book->title,
                'author' => $review->book->author,
                'rating' => $review->rating,
            ])
            ->values() // コレクションのインデックス（キー）を「0, 1, 2...」に綺麗にリセットする
            ->toArray();

        $stats = [
            'summary' => [
                'total_reviews' => $totalReviews,
                'books_read' => $booksRead,
                'average_rating' => $averageRating,
            ],
            'rating_distribution' => $ratingDistribution,
            'top_rated_books' => $topRatedBooks,
            'genre_ratings' => [],   // 後のタスクで実装します
        ];

        return view('reports.index', compact('stats'));
    }
}
