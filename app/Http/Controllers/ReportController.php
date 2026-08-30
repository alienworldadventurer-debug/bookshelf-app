<?php

namespace App\Http\Controllers;

use App\Models\ReadingPlan;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * マイ読書レポート画面の表示
     */
    public function index(Request $request): View
    {
        $user = auth()->user();

        // 1. ログインユーザーの全レビューを関連書籍・ジャンル込みで一括取得 (Eager Loading)
        $reviews = Review::with('book.genres')
            ->where('user_id', $user->id)
            ->get();

        // 2. ログインユーザーの完了済み読書計画を一括取得
        $completedPlans = ReadingPlan::where('user_id', $user->id)
            ->where('status', 'completed')
            ->get();

        // ====== 【タスク3】基本サマリーの集計 ======

        $totalReviews = $reviews->count();
        // 読了冊数は、読書計画テーブルでcompletedになっているユニークな書籍数を表示する
        $booksRead = $completedPlans->pluck('book_id')->unique()->count();
        $averageRating = $reviews->isEmpty() ? 0.0 : round($reviews->avg('rating'), 1);

        // ====== 【タスク4】評価分布の集計 ======

        $groupedReviews = $reviews->groupBy('rating');
        $actualDistribution = $groupedReviews->map(fn ($group) => $group->count());

        $mappedDistribution = $actualDistribution->mapWithKeys(function ($count, $rating) {
            return [$rating - 1 => $count];
        });

        // 画面の表示順（星1が上、星5が下）に合わせるため、0から4の昇順で並べます
        $ratingDistribution = collect([0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0])
            ->replace($mappedDistribution);

        // ====== 【タスク5】高評価書籍 TOP5 の集計 ======

        $filteredReviews = $reviews->filter(fn ($review) => $review->rating >= 4);

        // 同率スコア時のソート順：第2ソート（レビュー件数の降順）、第3ソート（登録IDの降順）
        $topReviews = $filteredReviews->sortByDesc(function ($review) {
            return [
                $review->rating,
                $review->book->reviews_count ?? 0,
                $review->book->id,
            ];
        })->take(5);

        $mappedBooks = $topReviews->map(fn ($review) => [
            'id' => $review->book->id,
            'title' => $review->book->title,
            'author' => $review->book->author,
            'rating' => $review->rating,
        ]);

        $topRatedBooks = $mappedBooks->values()->toArray();

        // ====== 【タスク6】ジャンル別評価傾向 TOP5 の集計（Collection活用） ======

        // 1. flatMap を使って「ジャンルID/名前」と「レビューの評価」のフラットなコレクションに平坦化する
        $flatGenreReviews = $reviews->flatMap(function ($review) {
            return $review->book->genres->map(function ($genre) use ($review) {
                return [
                    'genre_id' => $genre->id,
                    'genre_name' => $genre->name,
                    'rating' => $review->rating,
                ];
            });
        });

        // 2. ジャンルごとにグループ化し、各グループの「平均評価」と「件数」を計算する
        $groupedGenreReviews = $flatGenreReviews->groupBy('genre_id');
        $genreStats = $groupedGenreReviews->map(function ($items) {
            $firstItem = $items->first();

            return [
                'id' => $firstItem['genre_id'],
                'name' => $firstItem['genre_name'],
                'count' => $items->count(),
                'average_rating' => round($items->avg('rating'), 1),
            ];
        });

        // 3. 平均評価点の降順、同率時の第2ソート（レビュー件数の降順）、第3ソート（登録ID=ジャンルIDの降順）でソートし、上位5件を抽出する
        $genreRatings = $genreStats->sortByDesc(function ($genre) {
            return [
                $genre['average_rating'],
                $genre['count'],
                $genre['id'],
            ];
        })
            ->take(5)
            ->values()
            ->toArray();

        $stats = [
            'summary' => [
                'total_reviews' => $totalReviews,
                'books_read' => $booksRead,
                'average_rating' => $averageRating,
            ],
            'rating_distribution' => $ratingDistribution,
            'top_rated_books' => $topRatedBooks,
            'genre_ratings' => $genreRatings,
        ];

        return view('reports.index', compact('stats'));
    }
}
