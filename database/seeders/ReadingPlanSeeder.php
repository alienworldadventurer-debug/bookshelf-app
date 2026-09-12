<?php

namespace Database\Seeders;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ReadingPlanSeeder extends Seeder
{
    /**
     * リマインダーや認可検証に使用する読書計画の初期データを登録する。
     */
    public function run(): void
    {
        $yamada = User::where('email', 'yamada@example.com')->first();
        $suzuki = User::where('email', 'suzuki@example.com')->first();
        $books = Book::all();

        if (! $yamada || ! $suzuki || $books->count() < 6) {
            return;
        }

        $today = Carbon::today();

        ReadingPlan::create([
            'user_id' => $yamada->id,
            'book_id' => $books[0]->id,
            'target_date' => $today->copy()->addDays(3),
            'status' => ReadingPlanStatus::InProgress,
            'completed_at' => null,
        ]);

        ReadingPlan::create([
            'user_id' => $yamada->id,
            'book_id' => $books[1]->id,
            'target_date' => $today->copy(),
            'status' => ReadingPlanStatus::InProgress,
            'completed_at' => null,
        ]);

        ReadingPlan::create([
            'user_id' => $yamada->id,
            'book_id' => $books[2]->id,
            'target_date' => $today->copy()->subDays(3),
            'status' => ReadingPlanStatus::InProgress,
            'completed_at' => null,
        ]);

        ReadingPlan::create([
            'user_id' => $yamada->id,
            'book_id' => $books[3]->id,
            'target_date' => $today->copy()->addDays(7),
            'status' => ReadingPlanStatus::InProgress,
            'completed_at' => null,
        ]);

        ReadingPlan::create([
            'user_id' => $yamada->id,
            'book_id' => $books[4]->id,
            'target_date' => $today->copy()->subDays(10),
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => $today->copy()->subDays(5),
        ]);

        ReadingPlan::create([
            'user_id' => $suzuki->id,
            'book_id' => $books[5]->id,
            'target_date' => $today->copy()->addDays(5),
            'status' => ReadingPlanStatus::InProgress,
            'completed_at' => null,
        ]);
    }
}
