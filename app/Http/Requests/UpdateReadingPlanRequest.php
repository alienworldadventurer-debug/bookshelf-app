<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReadingPlanRequest extends FormRequest
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
     * 読書計画更新に使用するバリデーションルールを返す。
     *
     * @return array<string, array<int, string>> バリデーションルール
     */
    public function rules(): array
    {
        return [
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
            'target_date.required' => '期日は必須項目です。',
            'target_date.date' => '期日は正しい日付の形式で入力してください。',
            'target_date.after_or_equal' => '期日には本日以降の日付を指定してください。',
        ];
    }
}
