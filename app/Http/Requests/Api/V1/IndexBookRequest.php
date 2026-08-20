<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class IndexBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーションルール。
     */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:255'],
            'genre' => ['nullable', 'integer', 'exists:genres,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * 日本語エラーメッセージ。
     */
    public function messages(): array
    {
        return [
            'keyword.string' => 'キーワードはテキスト形式で入力してください。',
            'keyword.max' => 'キーワードは255文字以内で入力してください。',
            'genre.integer' => 'ジャンルIDは整数で入力してください。',
            'genre.exists' => '指定されたジャンルIDは存在しません。',
            'page.integer' => 'ページ番号は整数で入力してください。',
            'page.min' => 'ページ番号は1以上の整数で入力してください。',
            'per_page.integer' => '1ページあたりの件数は整数で入力してください。',
            'per_page.min' => '1ページあたりの件数は1以上の整数で入力してください。',
            'per_page.max' => '1ページあたりの件数は100以下の整数で入力してください。',
        ];
    }

    /**
     * バリデーション失敗時の挙動を上書き（オーバーライド）
     *
     * Laravel標準のエラーメッセージを封じ込め、
     * API仕様書に完全準拠したエラーJSONを強制的に返却します。
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => '入力内容に不備があります。', // 仕様書指定のメッセージ
            'errors' => $validator->errors(),           // 具体的なエラー内容一覧
        ], 422)); // ステータスコード 422（Unprocessable Entity）
    }
}
