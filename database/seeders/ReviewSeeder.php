<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $books = Book::all();

        // データのセーフティネット
        if ($users->count() < 5 || $books->count() < 11) {
            return;
        }

        // 応用要件：評価別日本語テンプレート（5段階）
        $templates = [
            5 => ['素晴らしい本でした！', '人生が変わりました。', '何度も読み返しています。'],
            4 => ['とても参考になりました。', '読みやすくておすすめです。', '期待通りの内容でした。'],
            3 => ['普通でした。', '可もなく不可もなく。', '期待したほどではなかった。'],
            2 => ['少し期待外れでした。', '内容が薄い印象。', 'もう少し深掘りしてほしかった。'],
            1 => ['残念ながら合いませんでした。', '期待と違いました。'],
        ];

        foreach ($books as $book) {
            // 💡 応用要件：レビュー件数をランダム（2〜4件）に決定
            $reviewCount = rand(2, 4);

            // 💡 応用要件：投稿者をその都度ランダムに選出（重複なし）
            $selectedUsers = $users->random($reviewCount);

            foreach ($selectedUsers as $user) {
                // 💡 応用要件：評価を「1〜5」に拡大
                $rating = rand(1, 5);

                // 5段階テンプレートからランダムにコメントを選択
                $commentArray = $templates[$rating];
                $comment = $commentArray[rand(0, count($commentArray) - 1)];

                Review::create([
                    'user_id' => $user->id,
                    'book_id' => $book->id,
                    'rating' => $rating,
                    'comment' => "【{$user->name}のレビュー】{$comment}",
                ]);
            }
        }
    }
}