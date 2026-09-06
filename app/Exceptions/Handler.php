<?php

namespace App\Exceptions;

use App\Models\Book;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException; // 💡 追加
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * バリデーション例外時にセッションにフラッシュされない入力のリスト。
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * アプリケーションの例外ハンドリングコールバックを登録します。
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // ==========================================
        // 💡 1. 403 未認可エラー：仕様書通り、errorキーで日本語返却
        // ==========================================
        $this->renderable(function (AuthorizationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'error' => 'この操作を実行する権限がありません。',
                ], 403);
            }
        });

        $this->renderable(function (AccessDeniedHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'error' => 'この操作を実行する権限がありません。',
                ], 403);
            }
        });

        // ==========================================
        // 💡 2. 404 書籍未検出（ルートモデル結合 ＆ 通常の未検出の両方に対応）
        // ==========================================
        // ① ルートモデル結合（/books/{book}）で書籍が見つからなかった場合
        $this->renderable(function (NotFoundHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $previous = $e->getPrevious();
                // 発生した原因が「Bookモデルの未検出」である場合のみ、仕様書通りのエラーを返す
                if ($previous instanceof ModelNotFoundException && $previous->getModel() === Book::class) {
                    return response()->json([
                        'error' => '指定された書籍が見つかりません。',
                    ], 404);
                }
            }
        });

        // ② コントローラー内でfindOrFailなどで直接モデルが見つからなかった場合
        $this->renderable(function (ModelNotFoundException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                if ($e->getModel() === Book::class) {
                    return response()->json([
                        'error' => '指定された書籍が見つかりません。',
                    ], 404);
                }
            }
        });

        // ==========================================
        // 💡 3. 422 バリデーションエラー：仕様書通り、messageキーに日本語文言
        // ==========================================
        $this->renderable(function (ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => '入力内容に不備があります。',
                    'errors' => $e->errors(),
                ], 422);
            }
        });
    }
}
