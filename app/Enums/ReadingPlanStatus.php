<?php

namespace App\Enums;

enum ReadingPlanStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Expired = 'expired';

    /**
     * 状態の日本語ラベルを取得
     */
    public function label(): string
    {
        return match ($this) {
            self::InProgress => '進行中',
            self::Completed => '読了',
            self::Expired => '期限切れ',
        };
    }

    /**
     * Tailwindのバッジ用CSSクラスを取得
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::InProgress => 'bg-blue-100 text-blue-800',
            self::Completed => 'bg-green-100 text-green-800',
            self::Expired => 'bg-red-100 text-red-800',
        };
    }
}
