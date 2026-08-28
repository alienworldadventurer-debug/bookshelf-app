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
     * Run the database seeds.
     */
    public function run(): void
    {
        // ユーザーを取得
        $yamada = User::where('email', 'yamada@example.com')->first();
        $suzuki = User::where('email', 'suzuki@example.com')->first();
        $books = Book::all();

        // 💡 データのセーフティネット：書籍が最低6冊以上あることを確認
        if (!$yamada || !$suzuki || $books->count() < 6) {
            return;
        }

        // --- 山田太郎の読書計画（主要シナリオ動作確認用：5件） ---

        // 1. 期日があと3日 ➔ 3日前リマインダー通知バッチのテスト対象
        ReadingPlan::create([
            'user_id' => $yamada->id,
            'book_id' => $books[0]->id, // 👈 1冊目の書籍
            'target_date' => Carbon::today()->addDays(3),
            'status' => ReadingPlanStatus::InProgress,
            'completed_at' => null,
        ]);

        // 2. 期日が今日 ➔ 当日リマインダー通知バッチのテスト対象
        ReadingPlan::create([
            'user_id' => $yamada->id,
            'book_id' => $books[1]->id, // 👈 2冊目の書籍
            'target_date' => Carbon::today(),
            'status' => ReadingPlanStatus::InProgress,
            'completed_at' => null,
        ]);

        // 3. 期日が3日前（進行中のまま） ➔ 自動失効バッチで「expired」に
        ReadingPlan::create([
            'user_id' => $yamada->id,
            'book_id' => $books[2]->id, // 👈 3冊目の書籍
            'target_date' => Carbon::today()->subDays(3),
            'status' => ReadingPlanStatus::InProgress,
            'completed_at' => null,
        ]);

        // 4. 期日があと7日 ➔ リマインダー通知の対象外データ
        ReadingPlan::create([
            'user_id' => $yamada->id,
            'book_id' => $books[3]->id, // 👈 4冊目の書籍
            'target_date' => Carbon::today()->addDays(7),
            'status' => ReadingPlanStatus::InProgress,
            'completed_at' => null,
        ]);

        // 5. 完了済み（読了）データ ➔ ダッシュボード集計などのテスト用
        ReadingPlan::create([
            'user_id' => $yamada->id,
            'book_id' => $books[4]->id, // 👈 5冊目の書籍
            'target_date' => Carbon::today()->subDays(10),
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => Carbon::today()->subDays(5),
        ]);


        // --- 鈴木花子の読書計画（他ユーザー認可テスト用：1件） ---

        // 6. 山田太郎でログイン中に、この計画（ID 6）の編集画面にアクセスした際に「403 Forbidden」を返すテスト用
        ReadingPlan::create([
            'user_id' => $suzuki->id,
            'book_id' => $books[5]->id, // 👈 6冊目の書籍
            'target_date' => Carbon::today()->addDays(5),
            'status' => ReadingPlanStatus::InProgress,
            'completed_at' => null,
        ]);
    }
}