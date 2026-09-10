<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Models\ReadingPlan;
use App\Models\Review;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * ログインユーザーの読書実績と評価傾向を集計して表示する。
     *
     * @return View 読書レポート画面
     */
    public function index(): View
    {
        $user = auth()->user();

        $reviews = Review::with([
            'book.genres',
            'book' => fn ($query) => $query->withCount('reviews'),
        ])
            ->where('user_id', $user->id)
            ->get();

        $completedPlans = ReadingPlan::where('user_id', $user->id)
            ->where('status', 'completed')
            ->get();

        $totalReviews = $reviews->count();
        $booksRead = $completedPlans->pluck('book_id')->unique()->count();
        $averageRating = $reviews->isEmpty() ? 0.0 : round($reviews->avg('rating'), 1);

        $groupedReviews = $reviews->groupBy('rating');
        $actualDistribution = $groupedReviews->map(fn (Collection $group): int => $group->count());

        $mappedDistribution = $actualDistribution->mapWithKeys(function (int $count, int|string $rating): array {
            return [$rating - 1 => $count];
        });

        $ratingDistribution = collect([0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0])
            ->replace($mappedDistribution);

        $filteredReviews = $reviews->filter(fn (Review $review): bool => $review->rating >= 4);

        $topReviews = $filteredReviews->sortByDesc(function (Review $review): array {
            return [
                $review->rating,
                $review->book->reviews_count,
                $review->book->id,
            ];
        })->take(5);

        $mappedBooks = $topReviews->map(fn (Review $review): array => [
            'id' => $review->book->id,
            'title' => $review->book->title,
            'author' => $review->book->author,
            'rating' => $review->rating,
        ]);

        $topRatedBooks = $mappedBooks->values()->toArray();

        $flatGenreReviews = $reviews->flatMap(function (Review $review): Collection {
            return $review->book->genres->map(function (Genre $genre) use ($review): array {
                return [
                    'genre_id' => $genre->id,
                    'genre_name' => $genre->name,
                    'rating' => $review->rating,
                ];
            });
        });

        $groupedGenreReviews = $flatGenreReviews->groupBy('genre_id');
        $genreStats = $groupedGenreReviews->map(function (Collection $items): array {
            $firstItem = $items->first();

            return [
                'id' => $firstItem['genre_id'],
                'name' => $firstItem['genre_name'],
                'count' => $items->count(),
                'average_rating' => round($items->avg('rating'), 1),
            ];
        });

        $genreRatings = $genreStats->sortByDesc(function (array $genre): array {
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
