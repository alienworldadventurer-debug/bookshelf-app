<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'author',
        'isbn',
        'published_date',
        'description',
        'image_url',
    ];

    /**
     * 書籍を登録したユーザー（多対1）
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 書籍に紐づくジャンル（多対多）
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'book_genre')->withTimestamps();
    }

    /**
     * 書籍に投稿されたレビュー一覧（1対多）
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * この書籍をお気に入り登録しているユーザー一覧（多対多）
     */
    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites', 'book_id', 'user_id')->withTimestamps();
    }

    /**
     * アクセサ: レビューの平均評価値を取得 (BookTest検証用)
     */
    public function getReviewsAvgRatingAttribute(): float
    {
        return (float) ($this->reviews()->avg('rating') ?? 0.0);
    }

    /**
     * アクセサ: レビュー総件数を取得 (BookTest検証用)
     */
    public function getReviewsCountAttribute(): int
    {
        return (int) $this->reviews()->count();
    }
}
