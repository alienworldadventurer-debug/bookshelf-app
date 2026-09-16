<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreBookRequest extends FormRequest
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
     * 書籍登録に使用するバリデーションルールを返す。
     *
     * @return array<string, array<int, string>> バリデーションルール
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'digits:13', 'unique:books,isbn'],
            'published_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'genres' => ['required', 'array', 'min:1'],
            'genres.*' => ['integer', 'exists:genres,id'],
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
            'title.required' => 'タイトルは必須項目です。',
            'title.string' => 'タイトルは文字列で入力してください。',
            'title.max' => 'タイトルは255文字以内で入力してください。',
            'author.required' => '著者は必須項目です。',
            'author.string' => '著者は文字列で入力してください。',
            'author.max' => '著者は255文字以内で入力してください。',
            'isbn.string' => 'ISBNは文字列で入力してください。',
            'isbn.digits' => 'ISBNはハイフンなしの13桁の半角数字で入力してください。',
            'isbn.unique' => 'このISBNは既に登録されています。',
            'published_date.date' => '出版日は正しい日付の形式で入力してください。',
            'description.string' => '説明は文字列で入力してください。',
            'description.max' => '説明は1000文字以内で入力してください。',
            'image_url.url' => '画像URLは正しいURL形式（https://...）で入力してください。',
            'image_url.max' => '画像URLは255文字以内で入力してください。',
            'genres.required' => 'ジャンルは選択必須です。',
            'genres.array' => 'ジャンルは正しい形式で選択してください。',
            'genres.min' => 'ジャンルは1つ以上選択してください。',
            'genres.*.integer' => 'ジャンルIDは整数で入力してください。',
            'genres.*.exists' => '指定されたジャンルIDは存在しません。',
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
