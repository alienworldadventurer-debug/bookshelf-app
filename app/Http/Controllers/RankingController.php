<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\View\View;

class RankingController extends Controller
{
    /**
     * 評価ランキング画面を表示する。
     *
     * @return View 評価ランキング画面
     */
    public function index(): View
    {
        $rankedBooks = Book::with(['genres'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->has('reviews')
            ->orderByDesc('reviews_avg_rating')
            ->orderByDesc('reviews_count')
            ->orderByDesc('id')
            ->take(10)
            ->get();

        return view('ranking.index', ['rankedBooks' => $rankedBooks]);
    }
}
