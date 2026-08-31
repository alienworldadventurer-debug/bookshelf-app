<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReadingPlanRequest extends FormRequest
{
    /**
     * リクエストの実行権限（認可）を判定します。
     *
     * 新規登録と同様、認可チェックはポリシー（Policy）側で
     * 制御するため、ここでは一律で「true（許可）」を返します。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 読書計画の編集（期日更新）時に適用するバリデーションルールを定義します。
     *
     * 編集時は書籍(book_id)の変更は許可せず、期日(target_date)のみの
     * 更新となるため、target_dateのルールのみを設定しています。
     */
    public function rules(): array
    {
        return [
            // 目標期日の検証
            'target_date' => [
                'required',             // 必須入力
                'date',                 // 正しい日付形式であること
                'after_or_equal:today', // 本日以降の日付（今日を含む未来の日付）であること
            ],
        ];
    }

    /**
     * バリデーションエラー発生時に返却する日本語エラーメッセージを定義します。
     */
    public function messages(): array
    {
        return [
            // 目標期日に関するメッセージ（StoreBookRequestの期日エラー文言とトーン＆マナーを統一）
            'target_date.required' => '期日は必須項目です。',
            'target_date.date' => '期日は正しい日付の形式で入力してください。',
            'target_date.after_or_equal' => '期日には本日以降の日付を指定してください。', // 仕様書（シート8）と完全一致
        ];
    }
}
