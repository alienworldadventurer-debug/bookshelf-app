<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * ログインユーザー宛ての通知一覧を表示する。
     *
     * @return View 通知一覧ビュー
     */
    public function index(): View
    {
        $notifications = auth()->user()->notifications;

        return view('notifications.index', compact('notifications'));
    }

    /**
     * 対象の通知を既読にする。
     *
     * @param  string  $id  既読にする通知のUUID
     * @return RedirectResponse 通知一覧画面へのリダイレクト
     */
    public function read(string $id): RedirectResponse
    {
        $notification = DatabaseNotification::findOrFail($id);

        if ((int) $notification->notifiable_id !== auth()->id()) {
            abort(403);
        }

        $notification->markAsRead();

        return back()->with('success', '通知を既読にしました。');
    }
}
