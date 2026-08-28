<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * マイグレーションの実行
     */
    public function up(): void
    {
        Schema::create('reading_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // ユーザー削除時連動
            $table->foreignId('book_id')->constrained()->cascadeOnDelete(); // 書籍削除時連動
            $table->date('target_date'); // 目標期日
            $table->string('status'); // ステータス (in_progress, completed, expired)
            $table->timestamp('completed_at')->nullable(); // 読了日時（任意）
            $table->timestamps();
        });
    }

    /**
     * マイグレーションのロールバック
     */
    public function down(): void
    {
        Schema::dropIfExists('reading_plans');
    }
};
