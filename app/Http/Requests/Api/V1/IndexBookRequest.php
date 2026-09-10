<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class IndexBookRequest extends FormRequest
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
     * 書籍一覧検索に使用するバリデーションルールを返す。
     *
     * @return array<string, array<int, string>> バリデーションルール
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
     * バリデーションエラーメッセージを返す。
     *
     * @return array<string, string> 入力項目ごとのエラーメッセージ
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
            'per_page.min' => '1ページあたりの件数は1以上の値を指定してください。',
            'per_page.max' => '1ページあたりの件数は100以下の値を指定してください。',
        ];
    }

    /**
     * バリデーション失敗時にAPI形式のエラーを返す。
     *
     * @param  Validator  $validator  バリデーション結果
     * @return never 常にHttpResponseExceptionを送出する
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'message' => '入力内容に不備があります。',
            'errors' => $validator->errors(),
        ], 422));
    }
}
