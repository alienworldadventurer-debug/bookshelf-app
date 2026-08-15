<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // 【追記】APIリクエストにおいて、データが見つからない（404）場合の日本語ハンドリング
        $this->renderable(function (NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                // ルートモデルバインディングなどの ModelNotFoundException が起因している場合
                if ($e->getPrevious() instanceof ModelNotFoundException) {
                    return response()->json([
                        'message' => '指定された書籍が見つかりません。',
                    ], 404);
                }
            }
        });
    }
}
