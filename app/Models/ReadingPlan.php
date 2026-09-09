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

    protected $fillable = [
        'user_id',
        'book_id',
        'target_date',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'status' => ReadingPlanStatus::class,
        'target_date' => 'date',
        'completed_at' => 'datetime',
    ];

    /**
     * 計画を所有するユーザーを取得する。
     *
     * @return BelongsTo<User, ReadingPlan>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 計画の対象となる書籍を取得する。
     *
     * @return BelongsTo<Book, ReadingPlan>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * 進行中の計画にクエリを絞り込む。
     *
     * @param  Builder<ReadingPlan>  $query
     * @return Builder<ReadingPlan>
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', ReadingPlanStatus::InProgress);
    }

    /**
     * 読了済みの計画にクエリを絞り込む。
     *
     * @param  Builder<ReadingPlan>  $query
     * @return Builder<ReadingPlan>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', ReadingPlanStatus::Completed);
    }

    /**
     * 期限切れの計画にクエリを絞り込む。
     *
     * @param  Builder<ReadingPlan>  $query
     * @return Builder<ReadingPlan>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', ReadingPlanStatus::Expired);
    }

    /**
     * 自動失効対象の計画にクエリを絞り込む。
     *
     * @param  Builder<ReadingPlan>  $query
     * @return Builder<ReadingPlan>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->inProgress()
            ->where('target_date', '<', Carbon::today());
    }

    /**
     * 計画が進行中で期日を過ぎているか判定する。
     *
     * @return bool 今日より前の日付であればtrue
     */
    public function isOverdue(): bool
    {
        return $this->status === ReadingPlanStatus::InProgress
            && $this->target_date->isPast()
            && ! $this->target_date->isToday();
    }
}
