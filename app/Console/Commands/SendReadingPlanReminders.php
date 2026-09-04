<?php

namespace App\Console\Commands;

use App\Models\ReadingPlan;
use App\Notifications\ReadingPlanReminder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

/**
 * クラス SendReadingPlanReminders
 *
 * 毎日20:00に実行され、以下の3つのバッチ処理をシーケンシャルに実行するコマンドクラス。
 * 1. 期限切れ読書計画の自動失効処理 (Auto-expire)
 * 2. 読了目標期限の「3日前」「当日」のリマインダー通知送信
 * 3. 自動失効から「3日後」の再エンゲージメント通知送信
 */
class SendReadingPlanReminders extends Command
{
    /**
     * コマンドのシグネチャ
     *
     * @var string
     */
    protected $signature = 'reading:send-reminders';

    /**
     * コマンドの説明
     *
     * @var string
     */
    protected $description = 'ユーザーの読書計画に基づき、自動失効処理、期日リマインダー通知、および再エンゲージメント通知を送信します。';

    /**
     * コマンドのコンストラクタ
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * コマンドのメイン処理を実行する
     *
     * @return int 終了ステータスコード (0: 正常終了)
     */
    public function handle(): int
    {
        Log::info('読書計画バッチ処理を開始します。');

        try {
            DB::transaction(function () {
                // 1. 自動失効（Auto-expire）の制御
                $this->autoExpirePlans();

                // 2. リマインダー通知（3日前・当日）の送信
                $this->sendDueReminders();

                // 3. 再エンゲージメント通知（失効3日後）の送信
                $this->sendReEngagementReminders();
            });

            Log::info('読書計画バッチ処理が正常に完了しました。');
            $this->info('読書計画バッチ処理が正常に完了しました。');

            return SymfonyCommand::SUCCESS;

        } catch (\Exception $e) {
            Log::error('読書計画バッチ処理中にエラーが発生しました: '.$e->getMessage());
            $this->error('バッチ処理エラー: '.$e->getMessage());

            return SymfonyCommand::FAILURE;
        }
    }

    /**
     * 目標期日を過ぎた進行中の計画を「期限切れ」に自動更新する
     */
    private function autoExpirePlans(): void
    {
        $yesterday = Carbon::yesterday()->endOfDay();

        // 期限（target_date）が昨日以前で、かつ進行中（in_progress）の計画を取得して一括更新
        $updatedCount = ReadingPlan::where('status', 'in_progress')
            ->where('target_date', '<=', $yesterday)
            ->update(['status' => 'expired']);

        Log::info("自動失効処理完了: {$updatedCount} 件の計画を期限切れ(expired)に変更しました。");
    }

    /**
     * 「目標期日の3日前」および「当日」の読書計画リマインダー通知を送信する
     */
    private function sendDueReminders(): void
    {
        $today = Carbon::today();
        $threeDaysLater = Carbon::today()->addDays(3);

        // ① 目標期日が3日後、かつ進行中（in_progress）の計画を取得
        $threeDaysBeforePlans = ReadingPlan::with(['user', 'book'])
            ->where('status', 'in_progress')
            ->whereDate('target_date', $threeDaysLater)
            ->get();

        foreach ($threeDaysBeforePlans as $plan) {
            $plan->user->notify(new ReadingPlanReminder($plan, 'three_days_before'));
        }

        // ② 目標期日が本日、かつ進行中（in_progress）の計画を取得
        $onDueDatePlans = ReadingPlan::with(['user', 'book'])
            ->where('status', 'in_progress')
            ->whereDate('target_date', $today)
            ->get();

        foreach ($onDueDatePlans as $plan) {
            $plan->user->notify(new ReadingPlanReminder($plan, 'on_due_date'));
        }

        Log::info('期日リマインダー通知送信完了: 3日前('.$threeDaysBeforePlans->count().'件), 当日('.$onDueDatePlans->count().'件)');
    }

    /**
     * 自動失効から「ちょうど3日後」が経過したユーザーへ再エンゲージメント通知を送信する
     */
    private function sendReEngagementReminders(): void
    {
        $threeDaysAgo = Carbon::today()->subDays(3);

        // 目標期日が3日前（失効から3日経過）で、期限切れ（expired）の計画を取得
        $expiredPlans = ReadingPlan::with(['user', 'book'])
            ->where('status', 'expired')
            ->whereDate('target_date', $threeDaysAgo)
            ->get();

        foreach ($expiredPlans as $plan) {
            $plan->user->notify(new ReadingPlanReminder($plan, 'three_days_after'));
        }

        Log::info("再エンゲージメント通知送信完了: 対象 {$expiredPlans->count()} 件に通知を送信しました。");
    }
}
