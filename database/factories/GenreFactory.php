<?php

namespace Database\Factories;

use App\Models\Genre;
use Illuminate\Database\Eloquent\Factories\Factory;

class GenreFactory extends Factory
{
    /**
     * 生成対象のモデルクラスを指定する。
     *
     * @var class-string<Genre>
     */
    protected $model = Genre::class;

    /**
     * ジャンルのテストデータを定義する。
     *
     * @return array<string, mixed> ジャンルの属性データ
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
        ];
    }
}
