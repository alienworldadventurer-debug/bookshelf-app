<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\TrustHosts;
use Tests\TestCase;

class TrustHostsTest extends TestCase
{
    /**
     * TrustHostsミドルウェアから信頼済みホストパターンを取得できることを検証する。
     *
     * @return void 取得結果が空でない配列であることを確認する
     */
    public function test_hosts_method_returns_array_of_trusted_patterns(): void
    {
        $middleware = new TrustHosts($this->app);

        $hosts = $middleware->hosts();

        $this->assertIsArray($hosts);
        $this->assertNotEmpty($hosts);
    }

    /**
     * 信頼済みホストからのリクエストがホストエラーで拒否されないことを検証する。
     *
     * @return void localhostからのアクセスがHTTP 400以外で処理されることを確認する
     */
    public function test_request_from_trusted_host_is_allowed(): void
    {
        $response = $this->withHeaders([
            'HOST' => 'localhost',
        ])->get('/');

        $this->assertNotEquals(400, $response->status());
    }
}
