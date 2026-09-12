<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    /**
     * 生成対象のモデルクラスを指定する。
     *
     * @var class-string<Review>
     */
    protected $model = Review::class;

    /**
     * レビューのテストデータを定義する。
     *
     * @return array<string, mixed> レビューの属性データ
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'book_id' => Book::factory(),
            'rating' => $this->faker->numberBetween(1, 5),
            'comment' => $this->faker->realText(200),
        ];
    }
}
