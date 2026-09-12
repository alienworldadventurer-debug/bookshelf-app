<?php

namespace App\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReadingPlanRequest extends FormRequest
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
     * 読書計画登録に使用するバリデーションルールを返す。
     *
     * @return array<string, array<int, mixed>> バリデーションルール
     */
    public function rules(): array
    {
        return [
            'book_id' => [
                'required',
                'integer',
                'exists:books,id',
                Rule::unique('reading_plans', 'book_id')
                    ->where(function (Builder $query): Builder {
                        return $query->where('user_id', $this->user()->id)
                            ->where('status', 'in_progress');
                    }),
            ],
            'target_date' => [
                'required',
                'date',
                'after_or_equal:today',
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
            'book_id.required' => '書籍を選択してください。',
            'book_id.integer' => '不正な書籍IDが送信されました。',
            'book_id.exists' => '選択された書籍は存在しません。',
            'book_id.unique' => 'この書籍に対する進行中の読書計画がすでに存在します。',
            'target_date.required' => '期日は必須項目です。',
            'target_date.date' => '期日は正しい日付の形式で入力してください。',
            'target_date.after_or_equal' => '期日には本日以降の日付を指定してください。',
        ];
    }
}
