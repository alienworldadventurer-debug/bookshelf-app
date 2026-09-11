<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/**
 * ユーザー本人のみが自身のプライベートチャンネルを購読できるよう認可する。
 *
 * @param  User  $user  認証済みユーザー
 * @param  int|string  $id  チャンネルに指定されたユーザーID
 * @return bool 認証済みユーザーが指定IDと一致するか
 */
Broadcast::channel('App.Models.User.{id}', function (User $user, int|string $id): bool {
    return (int) $user->id === (int) $id;
});
