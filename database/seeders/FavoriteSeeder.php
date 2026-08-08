<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $books = Book::all();

        if ($users->isEmpty() || $books->isEmpty()) {
            return;
        }

        foreach ($users as $user) {
            // ランダムに3〜5冊を抽出して紐付け
            $favCount = rand(3, 5);
            $randomBookIds = $books->random($favCount)->pluck('id')->toArray();

            $user->favoriteBooks()->syncWithoutDetaching($randomBookIds);
        }
    }
}
