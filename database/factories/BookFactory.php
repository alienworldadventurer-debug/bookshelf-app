<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookFactory extends Factory
{
    /**
     * 生成対象のモデルクラスを指定する。
     *
     * @var class-string<Book>
     */
    protected $model = Book::class;

    /**
     * 書籍のテストデータを定義する。
     *
     * @return array<string, mixed> 書籍の属性データ
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'author' => $this->faker->name(),
            'isbn' => $this->faker->unique()->isbn13(),
            'published_date' => $this->faker->date(),
            'description' => $this->faker->paragraph(),
            'image_url' => $this->faker->imageUrl(),
        ];
    }
}
