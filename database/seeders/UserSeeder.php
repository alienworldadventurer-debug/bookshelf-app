<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * 初期ユーザーを登録する。
     *
     * メールアドレスを一意キーとして既存ユーザーの重複作成を防止する。
     */
    public function run(): void
    {
        $users = [
            ['name' => '山田太郎', 'email' => 'yamada@example.com', 'password' => Hash::make('password')],
            ['name' => '鈴木花子', 'email' => 'suzuki@example.com', 'password' => Hash::make('password')],
            ['name' => '田中一郎', 'email' => 'tanaka@example.com', 'password' => Hash::make('password')],
            ['name' => '佐藤美咲', 'email' => 'sato@example.com', 'password' => Hash::make('password')],
            ['name' => '高橋健太', 'email' => 'takahashi@example.com', 'password' => Hash::make('password')],
        ];

        collect($users)->each(function (array $userData): void {
            User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        });
    }
}
