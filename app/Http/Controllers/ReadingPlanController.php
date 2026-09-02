<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\StoreReadingPlanRequest;
use App\Http\Requests\UpdateReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingPlanController extends Controller
{
    /**
     * ログインユーザー自身の読書計画一覧を表示します。
     *
     * @param  Request  $request  HTTPリクエストインスタンス
     * @return View 読書計画一覧ビュー（PG15）
     */
    public function index(Request $request): View
    {
        $currentStatus = $request->query('status');

        // 【N+1問題対策】リレーション先の書籍情報を Eager Loading で一括取得
        $query = auth()->user()->readingPlans()->with('book');

        if ($currentStatus && in_array($currentStatus, ['in_progress', 'completed', 'expired'])) {
            $query->where('status', $currentStatus);
        }

        $readingPlans = $query->get();

        return view('reading-plans.index', compact('readingPlans', 'currentStatus'));
    }

    /**
     * 新規読書計画作成画面を表示します。
     *
     * @return View 読書計画作成ビュー（PG16）
     */
    public function create(): View
    {
        // 進行中の計画がある本を除外するプルダウン制御
        $books = Book::whereDoesntHave('readingPlans', function ($query) {
            $query->where('user_id', auth()->id())
                ->where('status', ReadingPlanStatus::InProgress);
        })->get();

        return view('reading-plans.create', compact('books'));
    }

    /**
     * 読書計画を新規登録します。
     *
     * @param  StoreReadingPlanRequest  $request  FormRequest
     * @return RedirectResponse リダイレクト
     */
    public function store(StoreReadingPlanRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        auth()->user()->readingPlans()->create([
            'book_id' => $validated['book_id'],
            'target_date' => $validated['target_date'],
            'status' => ReadingPlanStatus::InProgress,
        ]);

        return redirect()->route('reading-plans.index')
            ->with('success', '読書計画を登録しました。');
    }

    /**
     * 読書計画の編集画面を表示します。
     *
     * @param  ReadingPlan  $readingPlan  読書計画モデル
     * @return View|RedirectResponse 編集ビュー、またはリダイレクト
     */
    public function edit(ReadingPlan $readingPlan): View|RedirectResponse
    {
        // Policyによる認可チェック（403ガード）
        $this->authorize('update', $readingPlan);

        // 完了済み計画は編集不可とするガード
        if ($readingPlan->status === ReadingPlanStatus::Completed) {
            return redirect()->route('reading-plans.index')
                ->with('error', '完了した読書計画は編集できません。');
        }

        return view('reading-plans.edit', compact('readingPlan'));
    }

    /**
     * 読書計画を更新します。
     *
     * @param  UpdateReadingPlanRequest  $request  FormRequest
     * @param  ReadingPlan  $readingPlan  読書計画モデル
     * @return RedirectResponse リダイレクト
     */
    public function update(UpdateReadingPlanRequest $request, ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('update', $readingPlan);

        if ($readingPlan->status === ReadingPlanStatus::Completed) {
            abort(403, '完了した読書計画は編集できません。');
        }

        $validated = $request->validated();

        // 期限切れから期日を延ばした時に進行中へと自動復帰させるロジック
        $status = $readingPlan->status;
        if ($status === ReadingPlanStatus::Expired) {
            $status = ReadingPlanStatus::InProgress;
        }

        $readingPlan->update([
            'target_date' => $validated['target_date'],
            'status' => $status,
        ]);

        return redirect()->route('reading-plans.index')
            ->with('success', '読書計画を更新しました。');
    }

    /**
     * 読書計画を物理削除します。
     *
     * @param  ReadingPlan  $readingPlan  読書計画モデル
     * @return RedirectResponse リダイレクト
     */
    public function destroy(ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('delete', $readingPlan);

        $readingPlan->delete();

        return redirect()->route('reading-plans.index')
            ->with('success', '読書計画を削除しました。');
    }

    /**
     * 読書計画を「読了（完了）」状態にします。
     *
     * @param  ReadingPlan  $readingPlan  読書計画モデル
     * @return RedirectResponse リダイレクト
     */
    public function complete(ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('update', $readingPlan);

        if ($readingPlan->status === ReadingPlanStatus::Completed) {
            abort(403, 'すでに完了している読書計画です。');
        }

        $readingPlan->update([
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => now(),
        ]);

        return redirect()->route('reading-plans.index')
            ->with('success', '読書計画を完了しました。');
    }
}
