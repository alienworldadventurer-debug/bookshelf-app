<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    /**
     * 書籍を編集・更新できるか判定する。
     *
     * @param  User  $user  認証済みユーザー
     * @param  Book  $book  対象書籍
     * @return bool 更新を許可する場合はtrue
     */
    public function update(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    /**
     * 書籍を削除できるか判定する。
     *
     * @param  User  $user  認証済みユーザー
     * @param  Book  $book  対象書籍
     * @return bool 削除を許可する場合はtrue
     */
    public function delete(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }
}
