<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GenreResource extends JsonResource
{
    /**
     * ジャンルリソースをAPIレスポンス用の配列に変換する。
     *
     * @param  Request  $request  現在のHTTPリクエスト
     * @return array<string, mixed> ジャンル情報のシリアライズ結果
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
