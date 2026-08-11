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

        // 評価（3〜5）別の具体的で自然な日本語コメント
        $comments = [
            5 => [
                '素晴らしい本でした！自分の価値観が大きく変わるような衝撃を受けました。',
                '人生のバイブルと言えるほどに役立つ内容でした。全員に一度は読んでほしいです。',
                '非常に内容が深く、終始惹き込まれました。何度も繰り返し読み返したくなります。',
            ],
            4 => [
                'とても参考になりました。具体的で分かりやすい解説が多かったです。',
                '非常に読みやすく、どんどん頭に入ってきます。買って損はありません。',
                '著者の見解に強く共感しました。明日からの生活に早速取り入れたい一冊です。',
            ],
            3 => [
                '普通に良かったです。基本的な内容が綺麗にまとまっていました。',
                '内容に一部共感できる章がありましたが、全体としては可もなく不可もなくでした。',
                '少し内容が薄い部分もありましたが、入門用の解説本としてはちょうど良いです。',
            ],
        ];

        // 11冊の書籍それぞれに作成するレビュー数を定義（正確に合計32件、各書籍2〜4件の範囲内）
        $distribution = array_fill(0, 10, 3);
        $distribution[1] = 2;

        foreach ($books as $index => $book) {
            $numReviews = $distribution[$index] ?? 3;

            // 💡重要：5人の全ユーザーから、この本にレビューを書く「重複しないユーザー」を必要数（2〜4人）だけ一括でランダム選出！
            $selectedUsers = $users->random($numReviews);

            // 選ばれた一意な（重複のない）ユーザーたちでループを回して登録
            foreach ($selectedUsers as $user) {
                $rating = rand(3, 5); // 評価値: 3〜5

                $commentArray = $comments[$rating];
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
