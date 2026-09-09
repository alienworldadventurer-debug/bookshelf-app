<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * ログインユーザー宛ての通知一覧を表示します。
     *
     * @param  Request  $request  HTTPリクエストインスタンス
     * @return View 通知一覧ビュー（PG18）
     */
    public function index(Request $request): View
    {
        // ログインユーザー宛てのすべての通知（未読・既読含む）を最新順に取得します
        // ※Laravel標準のNotifiableトレイトが提供する notifications リレーションを利用します
        $notifications = auth()->user()->notifications;

        // 通知一覧画面（resources/views/notifications/index.blade.php）にデータを渡して描画します
        return view('notifications.index', compact('notifications'));
    }

    /**
     * 対象の通知を「既読」状態にします。
     *
     * @param  string  $id  既読化する通知のUUID（Laravel標準はchar36形式）
     * @return RedirectResponse 元の通知一覧画面へのリダイレクトバック
     */
    public function read(string $id): RedirectResponse
    {
        // 1. 全通知データから対象IDの通知を取得（存在しないIDの場合は 404 Not Found）
        $notification = DatabaseNotification::findOrFail($id);

        // 2. 所有者チェック：通知の宛先がログインユーザー自身でない場合は 403 Forbidden エラーを発生
        if ((int) $notification->notifiable_id !== auth()->id()) {
            abort(403);
        }

        // 3. read_at カラムに現在日時をセットして「既読」にします
        $notification->markAsRead();

        // 成功のフラッシュメッセージを伴って、元の通知一覧画面へリダイレクトバックします
        return back()->with('success', '通知を既読にしました。');
    }
}
