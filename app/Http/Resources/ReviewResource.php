<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    /**
     * レビューリソースをAPIレスポンス用の配列に変換する。
     *
     * @param  Request  $request  現在のHTTPリクエスト
     * @return array<string, mixed> レビュー情報のシリアライズ結果
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_name' => $this->user ? $this->user->name : null,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
