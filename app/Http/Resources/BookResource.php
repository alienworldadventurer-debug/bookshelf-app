<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    /**
     * 書籍リソースをAPIレスポンス用の配列に変換する。
     *
     * @param  Request  $request  現在のHTTPリクエスト
     * @return array<string, mixed> 書籍情報のシリアライズ結果
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
            'genres' => GenreResource::collection($this->whenLoaded('genres')),

            'reviews_avg_rating' => $this->reviews_avg_rating !== null ? (float) number_format($this->reviews_avg_rating, 1) : null,
            'reviews_count' => (int) $this->reviews_count,

            'reviews' => $this->whenLoaded('reviews', function (): AnonymousResourceCollection {
                return ReviewResource::collection($this->reviews);
            }),

            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }
}
