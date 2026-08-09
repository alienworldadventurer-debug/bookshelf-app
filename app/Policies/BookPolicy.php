<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    /**
     * 書籍を編集・更新できるか判定
     */
    public function update(User $user, Book $book): bool
    {
        // ログイン中のユーザーIDと、書籍の登録者IDが一致している場合のみ許可
        return $user->id === $book->user_id;
    }

    /**
     * 書籍を削除できるか判定
     */
    public function delete(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }
}
