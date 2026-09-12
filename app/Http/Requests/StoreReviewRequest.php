<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    /**
     * リクエストを実行する権限があるか判断する。
     *
     * @return bool リクエストを許可する場合はtrue
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * レビュー登録に使用するバリデーションルールを返す。
     *
     * @return array<string, array<int, string>> バリデーションルール
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'in:1,2,3,4,5'],
            'comment' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * バリデーションエラーメッセージを返す。
     *
     * @return array<string, string> 入力項目ごとのエラーメッセージ
     */
    public function messages(): array
    {
        return [
            'rating.required' => '評価は選択必須です。',
            'rating.integer' => '評価は数値形式で選択してください。',
            'rating.in' => '評価は1〜5の範囲で選択してください。',
            'comment.string' => 'コメントは文字列で入力してください。',
            'comment.max' => 'コメントは1000文字以内で入力してください。',
        ];
    }
}
