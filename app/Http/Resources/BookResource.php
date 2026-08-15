<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    /**
     * リソースを配列に変換します。
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,
            'published_date' => $this->published_date,
            'description' => $this->description,
            'image_url' => $this->image_url,
            // genresリレーションがロードされていれば、GenreResource形式で出力
            'genres' => GenreResource::collection($this->whenLoaded('genres')),

            // 平均評価（reviews_avg_rating）を小数第1位で出力（なければ null）
            'reviews_avg_rating' => $this->reviews_avg_rating !== null ? (float) number_format($this->reviews_avg_rating, 1) : null,
            'reviews_count' => (int) $this->reviews_count,

            // reviewsリレーションがロードされている（詳細API）場合のみ、ReviewResource形式の配列をネストする
            'reviews' => $this->whenLoaded('reviews', function () {
                return ReviewResource::collection($this->reviews);
            }),

            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }
}
