<?php

namespace App\Models;

use App\Enums\ReadingPlanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
