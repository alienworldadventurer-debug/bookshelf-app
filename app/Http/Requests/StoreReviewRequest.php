<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    /**
     * リクエストの認可チェック
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーションルールの定義
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'in:1,2,3,4,5'],
            'comment' => [
                'nullable',
                'string',
                'max:1000',
                // 【重複投稿防止】同一ユーザーによる同じ書籍への複数投稿をブロック
                function ($attribute, $value, $fail) {
                    $book = $this->route('book'); // リクエストパラメータから書籍モデルを取得
                    if ($book) {
                        $hasReviewed = \App\Models\Review::where('book_id', $book->id)
                            ->where('user_id', $this->user()->id)
                            ->exists();

                        if ($hasReviewed) {
                            $fail('この書籍には既にレビューを投稿しています。');
                        }
                    }
                }
            ],
        ];
    }

    /**
     * 日本語エラーメッセージの定義
     */
    public function messages(): array
    {
        return [
            'rating.required' => '評価は選択必須です。',
            'rating.integer' => '評価は数値形式で選択してください。',
            'rating.in' => '評価は1〜5の範囲で選択してください。',
            'comment.string' => 'コメントはテキスト形式で入力してください。',
            'comment.max' => 'コメントは1000文字以内で入力してください。',
        ];
    }
}