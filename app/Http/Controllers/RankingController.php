<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\View\View;

class RankingController extends Controller
{
    /**
     * 評価ランキング画面の表示 (index)
     */
    public function index(): View
    {
        // 平均評価TOP10の書籍を、各種ソート要件・N+1対策を施した上で高速に取得します
        $rankedBooks = Book::with(['genres'])
            ->withAvg('reviews', 'rating') // 平均評価を reviews_avg_rating として取得
            ->withCount('reviews')         // レビュー件数を reviews_count として取得
            ->has('reviews')               // レビューが1件以上ある書籍のみに絞り込む（レビューなしを除外）
            ->orderByDesc('reviews_avg_rating') // 【第1ソート】平均評価の降順
            ->orderByDesc('reviews_count')      // 【第2ソート】同スコア時はレビュー件数の降順
            ->orderByDesc('id')                 // 【第3ソート】さらに同点時は書籍登録IDの降順
            ->take(10)                          // TOP 10件に制限
            ->get();

        // 取得した書籍データをBlade（PG11 ランキング）に渡して表示を返します
        return view('ranking.index', ['rankedBooks' => $rankedBooks]);
    }
}