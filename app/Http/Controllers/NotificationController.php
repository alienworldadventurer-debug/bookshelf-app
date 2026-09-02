<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        // 【セキュリティ・所有者チェックの徹底】
        // ログインユーザー自身の未読通知（unreadNotifications）の中から対象IDを検索します。
        // もし他人の通知IDや、存在しないIDが送信されてきた場合は「404 Not Found」として安全に弾き飛ばします。
        $notification = auth()->user()->unreadNotifications()->findOrFail($id);

        // read_at カラムに現在日時をセットして「既読」にします
        $notification->markAsRead();

        // 成功のフラッシュメッセージを伴って、元の通知一覧画面へリダイレクトバックします
        return back()->with('success', '通知を既読にしました。');
    }
}
