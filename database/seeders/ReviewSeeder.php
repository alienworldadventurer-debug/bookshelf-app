<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * 書籍ごとに評価とレビュー本文の初期データを登録する。
     *
     * 各書籍に対して2〜4人のユーザーをランダムに選択し、
     * 評価に応じた日本語テンプレートからレビューを生成する。
     */
    public function run(): void
    {
        $users = User::all();
        $books = Book::all();

        if ($users->count() < 5 || $books->count() < 11) {
            return;
        }

        $templates = [
            5 => ['素晴らしい本でした！', '人生が変わりました。', '何度も読み返しています。'],
            4 => ['とても参考になりました。', '読みやすくておすすめです。', '期待通りの内容でした。'],
            3 => ['普通でした。', '可もなく不可もなく。', '期待したほどではなかった。'],
            2 => ['少し期待外れでした。', '内容が薄い印象。', 'もう少し深掘りしてほしかった。'],
            1 => ['残念ながら合いませんでした。', '期待と違いました。'],
        ];

        $books->each(function (Book $book) use ($users, $templates): void {
            $reviewCount = rand(2, 4);
            $selectedUsers = $users->random($reviewCount);

            $selectedUsers->each(function (User $user) use ($book, $templates): void {
                $rating = rand(1, 5);
                $commentArray = $templates[$rating];
                $comment = $commentArray[rand(0, count($commentArray) - 1)];

                Review::create([
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                    'rating' => $rating,
                    'comment' => "【{$user->name}のレビュー】{$comment}",
                ]);
            });
        });
    }
}
