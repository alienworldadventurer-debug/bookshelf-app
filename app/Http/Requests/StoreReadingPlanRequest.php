<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReadingPlanRequest extends FormRequest
{
    /**
     * リクエストの実行権限（認可）を判定します。
     *
     * 認可制御はポリシー（Policy）を用いて一元管理するため、
     * FormRequest内でのチェックは一律で「true（許可）」としています。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 読書計画の新規登録時に適用するバリデーションルールを定義します。
     */
    public function rules(): array
    {
        return [
            // 対象書籍ID(book_id)の検証
            'book_id' => [
                'required',        // 必須入力
                'integer',         // 整数形式であること
                'exists:books,id', // 実際にbooksテーブルに存在しているIDであること

                // 【重要：進行中計画の重複制御ロジック】
                // ログインユーザー自身が、対象の書籍(book_id)に対して、
                // すでにステータスが「進行中(in_progress)」の計画を持っていないかをデータベースから検索して検証します。
                Rule::unique('reading_plans', 'book_id')
                    ->where(function ($query) {
                        return $query->where('user_id', $this->user()->id) // ログインユーザー自身のレコードに限定
                            ->where('status', 'in_progress');    // ステータスが「進行中」のもの
                    }),
            ],
            // 目標期日(target_date)の検証
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
            // 書籍IDに関するメッセージ
            'book_id.required' => '書籍を選択してください。',
            'book_id.integer' => '不正な書籍IDが送信されました。',
            'book_id.exists' => '選択された書籍は存在しません。',
            'book_id.unique' => 'この書籍に対する進行中の読書計画がすでに存在します。', // 仕様書（シート8）と完全一致

            // 目標期日に関するメッセージ
            'target_date.required' => '期日は必須項目です。',
            'target_date.date' => '期日は正しい日付の形式で入力してください。',
            'target_date.after_or_equal' => '期日には本日以降の日付を指定してください。', // 仕様書（シート8）と完全一致
        ];
    }
}
