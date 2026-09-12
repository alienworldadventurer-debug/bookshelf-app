<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    /**
     * ユーザーごとにランダムな書籍をお気に入りへ登録する。
     */
    public function run(): void
    {
        $users = User::all();
        $books = Book::all();

        if ($users->isEmpty() || $books->isEmpty()) {
            return;
        }

        $users->each(function (User $user) use ($books): void {
            $favCount = min(random_int(3, 5), $books->count());
            $randomBookIds = $books->random($favCount)->pluck('id')->toArray();

            $user->favoriteBooks()->syncWithoutDetaching($randomBookIds);
        });
    }
}
