<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // ==========================================
    // リレーション定義 (ユーザー関連)
    // ==========================================

    /**
     * ユーザーが登録した書籍一覧（1対多）
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    /**
     * ユーザーが投稿したレビュー一覧（1対多）
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * ユーザーがお気に入り登録した書籍一覧（多対多）
     */
    public function favoriteBooks(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'favorites', 'user_id', 'book_id')->withTimestamps();
    }

    /**
     * ユーザーが「いいね」したレビュー一覧（多対多）
     */
    public function likedReviews(): BelongsToMany
    {
        return $this->belongsToMany(Review::class, 'review_likes', 'user_id', 'review_id')->withTimestamps();
    }

    /**
     * ユーザーが所有する読書計画一覧を取得
     */
    public function readingPlans(): HasMany
    {
        return $this->hasMany(ReadingPlan::class);
    }
}
