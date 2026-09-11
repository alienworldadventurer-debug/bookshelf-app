<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingPlan>
 */
class ReadingPlanFactory extends Factory
{
    /**
     * 生成対象のモデルクラスを指定する。
     *
     * @var class-string<ReadingPlan>
     */
    protected $model = ReadingPlan::class;

    /**
     * 読書計画のテストデータを定義する。
     *
     * @return array<string, mixed> 読書計画の属性データ
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'book_id' => Book::factory(),
            'target_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => 'in_progress',
        ];
    }
}
