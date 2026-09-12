<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\StoreReadingPlanRequest;
use App\Http\Requests\UpdateReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingPlanController extends Controller
{
    /**
     * ログインユーザー自身の読書計画一覧を表示する。
     *
     * @param  Request  $request  ステータス絞り込み条件を含むリクエスト
     * @return View 読書計画一覧ビュー
     */
    public function index(Request $request): View
    {
        $currentStatus = $request->query('status');

        $query = auth()->user()->readingPlans()->with('book');

        if ($currentStatus && in_array($currentStatus, ['in_progress', 'completed', 'expired'])) {
            $query->where('status', $currentStatus);
        }

        $readingPlans = $query->get();

        return view('reading-plans.index', compact('readingPlans', 'currentStatus'));
    }

    /**
     * 新規読書計画作成画面を表示する。
     *
     * @return View 読書計画作成ビュー
     */
    public function create(): View
    {
        $books = Book::whereDoesntHave('readingPlans', function (Builder $query): void {
            $query->where('user_id', auth()->id())
                ->where('status', ReadingPlanStatus::InProgress);
        })->get();

        return view('reading-plans.create', compact('books'));
    }

    /**
     * 読書計画を新規登録する。
     *
     * @param  StoreReadingPlanRequest  $request  登録する読書計画情報
     * @return RedirectResponse 読書計画一覧画面へのリダイレクト
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
     * 読書計画の編集画面を表示する。
     *
     * @param  ReadingPlan  $readingPlan  編集対象の読書計画
     * @return View|RedirectResponse 編集画面、または一覧画面へのリダイレクト
     */
    public function edit(ReadingPlan $readingPlan): View|RedirectResponse
    {
        $this->authorize('update', $readingPlan);

        if ($readingPlan->status === ReadingPlanStatus::Completed) {
            return redirect()->route('reading-plans.index')
                ->with('error', '完了した読書計画は編集できません。');
        }

        return view('reading-plans.edit', compact('readingPlan'));
    }

    /**
     * 読書計画を更新する。
     *
     * @param  UpdateReadingPlanRequest  $request  更新する読書計画情報
     * @param  ReadingPlan  $readingPlan  更新対象の読書計画
     * @return RedirectResponse 読書計画一覧画面へのリダイレクト
     */
    public function update(UpdateReadingPlanRequest $request, ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('update', $readingPlan);

        if ($readingPlan->status === ReadingPlanStatus::Completed) {
            abort(403, '完了した読書計画は編集できません。');
        }

        $validated = $request->validated();

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
     * 読書計画を削除する。
     *
     * @param  ReadingPlan  $readingPlan  削除対象の読書計画
     * @return RedirectResponse 読書計画一覧画面へのリダイレクト
     */
    public function destroy(ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('delete', $readingPlan);

        $readingPlan->delete();

        return redirect()->route('reading-plans.index')
            ->with('success', '読書計画を削除しました。');
    }

    /**
     * 読書計画を完了状態にする。
     *
     * @param  ReadingPlan  $readingPlan  完了対象の読書計画
     * @return RedirectResponse 読書計画一覧画面へのリダイレクト
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
