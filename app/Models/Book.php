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

    protected $casts = [
        'published_date' => 'date',
    ];

    /**
     * 書籍を登録したユーザーを取得する。
     *
     * @return BelongsTo<User, Book>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 書籍に紐づくジャンルを取得する。
     *
     * @return BelongsToMany<Genre, Book>
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'book_genre')->withTimestamps();
    }

    /**
     * 書籍に投稿されたレビューを取得する。
     *
     * @return HasMany<Review, Book>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * この書籍をお気に入り登録しているユーザーを取得する。
     *
     * @return BelongsToMany<User, Book>
     */
    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites', 'book_id', 'user_id')->withTimestamps();
    }

    /**
     * レビューの平均評価値を取得する。
     *
     * @return float レビューがない場合は0.0
     */
    public function getReviewsAvgRatingAttribute(): float
    {
        return (float) ($this->reviews()->avg('rating') ?? 0.0);
    }

    /**
     * レビュー総件数を取得する。
     *
     * @return int レビューの件数
     */
    public function getReviewsCountAttribute(): int
    {
        return (int) $this->reviews()->count();
    }

    /**
     * 書籍に関連する読書計画を取得する。
     *
     * @return HasMany<ReadingPlan, Book>
     */
    public function readingPlans(): HasMany
    {
        return $this->hasMany(ReadingPlan::class);
    }
}
