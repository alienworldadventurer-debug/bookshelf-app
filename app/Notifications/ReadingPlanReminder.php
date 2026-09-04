<?php

namespace App\Notifications;

use App\Models\ReadingPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReadingPlanReminder extends Notification
{
    use Queueable;

    /**
     * トリガーとなった読書計画モデル
     */
    public ReadingPlan $readingPlan;

    /**
     * 通知タイミング ('three_days_before' | 'on_due_date' | 'three_days_after')
     */
    public string $timing;

    /**
     * 新しい通知インスタンスを生成します。
     *
     * @param  ReadingPlan  $readingPlan  読書計画モデル
     * @param  string  $timing  配信タイミングの識別子
     */
    public function __construct(ReadingPlan $readingPlan, string $timing)
    {
        $this->readingPlan = $readingPlan;
        $this->timing = $timing;
    }

    /**
     * 通知の配信チャネルを定義します。
     * 要件に従い、メール送信は行わず、データベース（DatabaseChannel）にのみ保存します。
     *
     * @param  mixed  $notifiable  通知を受け取るUserモデルなどのインスタンス
     * @return array<int, string> 配信チャネルの配列
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /**
     * データベースの notifications テーブルの data カラム（JSON）に保存する配列を定義します。
     * notifications_index.blade.php のデザインや出し分け仕様と完全に連動しています。
     *
     * @param  mixed  $notifiable  通知を受け取るインスタンス
     * @return array<string, mixed> 保存するデータ配列
     */
    public function toArray(mixed $notifiable): array
    {
        $bookTitle = $this->readingPlan->book->title;
        $dueDate = $this->readingPlan->target_date->format('Y-m-d');

        // タイミングごとの件名（title）と本文（body）の動的な組み立て
        $title = '';
        $body = '';

        switch ($this->timing) {
            case 'three_days_before':
                $title = '【リマインダー】読書期日の3日前です';
                $body = "計画している「{$bookTitle}」の読了期日（{$dueDate}）まであと3日です。計画的に読み進めましょう！";
                break;
            case 'on_due_date':
                $title = '【リマインダー】読書期日の当日です';
                $body = "計画している「{$bookTitle}」の読了期日は本日（{$dueDate}）です。読了しましたら読書計画画面から「読了する」ボタンを押してください。";
                break;
            case 'three_days_after':
                $title = '【再挑戦】もう一度計画を立ててみませんか？';
                $body = "期限切れとなった「{$bookTitle}」について、もう一度計画を立て直して読書を再開してみませんか？";
                break;
            default:
                $title = '読書計画のお知らせ';
                $body = "計画している「{$bookTitle}」に関するお知らせです。";
                break;
        }

        return [
            'reading_plan_id' => $this->readingPlan->id, // トリガーとなった計画の特定用
            'timing' => $this->timing,                   // UIデザイン（アイコン・ボーダー色）の切り替え用
            'title' => $title,                           // 通知の件名
            'body' => $body,                             // 通知の本文
        ];
    }
}
