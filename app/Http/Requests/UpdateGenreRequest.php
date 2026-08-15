<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGenreRequest extends FormRequest
{
    /**
     * ユーザーがこのリクエストを行う権限があるかどうかを判定します
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * リクエストに適用するバリデーションルールを定義します
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('genres', 'name')->ignore($this->genre),
            ],
        ];
    }

    /**
     * 定義済みバリデーションルールのエラーメッセージを取得します
     */
    public function messages(): array
    {
        return [
            'name.required' => 'ジャンル名は必須項目です。',
            'name.string' => 'ジャンル名はテキスト形式で入力してください。',
            'name.max' => 'ジャンル名は255文字以内で入力してください。',
            'name.unique' => 'このジャンル名は既に存在します。',
        ];
    }
}
