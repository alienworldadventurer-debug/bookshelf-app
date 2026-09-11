<?php

namespace App\Policies;

use App\Models\ReadingPlan;
use App\Models\User;

class ReadingPlanPolicy
{
    /**
     * ユーザーが対象の読書計画を更新（期日変更・読了処理）できるか判定します。
     *
     * @param  User  $user  ログイン中のユーザーインスタンス
     * @param  ReadingPlan  $readingPlan  対象となる読書計画インスタンス
     * @return bool 操作権限がある場合は true、ない場合は false
     */
    public function update(User $user, ReadingPlan $readingPlan): bool
    {
        return $this->ownsPlan($user, $readingPlan);
    }

    /**
     * ユーザーが対象の読書計画を削除できるか判定します。
     *
     * @param  User  $user  ログイン中のユーザーインスタンス
     * @param  ReadingPlan  $readingPlan  対象となる読書計画インスタンス
     * @return bool 操作権限がある場合は true、ない場合は false
     */
    public function delete(User $user, ReadingPlan $readingPlan): bool
    {
        return $this->ownsPlan($user, $readingPlan);
    }

    /**
     * 読書計画がユーザーの所有であるか判定します。
     *
     * @param  User  $user  ログイン中のユーザーインスタンス
     * @param  ReadingPlan  $readingPlan  対象となる読書計画インスタンス
     * @return bool ユーザーが計画を所有している場合はtrue
     */
    private function ownsPlan(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id;
    }
}
