<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\TrustHosts;
use Tests\TestCase;

class TrustHostsTest extends TestCase
{
    /**
     * @test
     * 正常系: ミドルウェアが定義する「信頼できるホストパターン」のリスト（配列）が
     * 正しく取得できること。
     */
    public function test_hosts_method_returns_array_of_trusted_patterns(): void
    {
        // ミドルウェアをインスタンス化
        $middleware = new TrustHosts($this->app);

        // hosts()メソッドを直接実行
        $hosts = $middleware->hosts();

        // 戻り値が配列であり、空ではないことを検証
        $this->assertIsArray($hosts);
        $this->assertNotEmpty($hosts);
    }

    /**
     * @test
     * 正常系: 信頼されたホスト（例: localhost）からのアクセス要求が
     * ブロックされずに正常に通過すること。
     */
    public function test_request_from_trusted_host_is_allowed(): void
    {
        // 信頼された標準ホスト（localhost）をヘッダーにセットしてリクエストを送信
        $response = $this->withHeaders([
            'HOST' => 'localhost',
        ])->get('/');

        // 400 Bad Request（不正なホストエラー）にならず、正常なレスポンスが返ることを検証
        $this->assertNotEquals(400, $response->status());
    }
}
