<?php

namespace App\Models;

use App\Enums\ReadingPlanStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\ReadingPlan
 *
 * @property Carbon $target_date
 * @property Carbon|null $completed_at
 */
class ReadingPlan extends Model
{
    use HasFactory;

    /**
     * 複数代入可能な属性
     */
    protected $fillable = [
        'user_id',
        'book_id',
        'target_date',
        'status',
        'completed_at',
    ];

    /**
     * 属性のキャストルール
     */
    protected $casts = [
        'status' => ReadingPlanStatus::class, // Enumにキャスト
        'target_date' => 'date',
        'completed_at' => 'datetime',
    ];

    /**
     * 計画を所有するユーザーとの多対1リレーション
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 対象の書籍との多対1リレーション
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    // ==========================================
    // クエリスコープ（Scopes）の整備
    // ==========================================

    /**
     * 進行中の計画のみに絞り込むスコープ
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', ReadingPlanStatus::InProgress);
    }

    /**
     * 読了済みの計画のみに絞り込むスコープ
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', ReadingPlanStatus::Completed);
    }

    /**
     * 期限切れの計画のみに絞り込むスコープ
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', ReadingPlanStatus::Expired);
    }

    /**
     * 自動失効バッチ用のスコープ
     * （ステータスが進行中、かつ期日が昨日以前のものを抽出する）
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->inProgress()
            ->where('target_date', '<', Carbon::today());
    }

    // ==========================================
    // カスタムヘルパーメソッド
    // ==========================================

    /**
     * 現在、計画が期日を過ぎている（期限切れ状態であるべきか）判定する
     */
    public function isOverdue(): bool
    {
        return $this->status === ReadingPlanStatus::InProgress
            && $this->target_date->isPast()
            && ! $this->target_date->isToday();
    }
}
