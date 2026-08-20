<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Book;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateBookRequest extends FormRequest
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
        // ルートパラメータからバインドされた書籍IDを取得
        $book = $this->route('book');
        $bookId = $book instanceof Book ? $book->id : $book;

        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => [
                'required',
                'string',
                'digits:13',
                Rule::unique('books', 'isbn')->ignore($bookId),
            ],
            'published_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'genres' => ['required', 'array', 'min:1'],
            'genres.*' => ['integer', 'exists:genres,id'],
        ];
    }

    /**
     * 日本語エラーメッセージ。
     */
    public function messages(): array
    {
        return [
            'title.required' => 'タイトルは必須項目です。',
            'title.string' => 'タイトルはテキスト形式で入力してください。',
            'title.max' => 'タイトルは255文字以内で入力してください。',
            'author.required' => '著者は必須項目です。',
            'author.string' => '著者はテキスト形式で入力してください。',
            'author.max' => '著者は255文字以内で入力してください。',
            'isbn.required' => 'ISBNは必須項目です。',
            'isbn.string' => 'ISBNはテキスト形式で入力してください。',
            'isbn.digits' => 'ISBNはハイフンなしの13桁の半角数字で入力してください。',
            'isbn.unique' => 'このISBNは既に登録されています。',
            'published_date.required' => '出版日は必須項目です。',
            'published_date.date' => '出版日は正しい日付の形式で入力してください。',
            'description.string' => '説明はテキスト形式で入力してください。',
            'description.max' => '説明は1000文字以内で入力してください。',
            'image_url.url' => '画像URLは正しいURL形式（https://...）で入力してください。',
            'image_url.max' => '画像URLは255文字以内で入力してください。',
            'genres.required' => 'ジャンルは1つ以上選択してください。',
            'genres.array' => 'ジャンルは正しい形式で選択してください。',
            'genres.min' => 'ジャンルは1つ以上選択してください。',
            'genres.*.integer' => '選択されたジャンルIDは整数でなければなりません。',
            'genres.*.exists' => '選択されたジャンルは存在しません。',
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
