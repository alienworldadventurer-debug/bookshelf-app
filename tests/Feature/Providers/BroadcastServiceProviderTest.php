<?php

namespace Tests\Feature\Providers;

use App\Providers\BroadcastServiceProvider;
use Tests\TestCase;

class BroadcastServiceProviderTest extends TestCase
{
    /**
     * @test
     * 正常系: BroadcastServiceProvider が例外を発生させることなく、
     * 正常に起動（boot）できること。
     */
    public function test_broadcast_service_provider_boots_successfully(): void
    {
        // サービスプロバイダをインスタンス化
        $provider = new BroadcastServiceProvider($this->app);

        // bootメソッドを直接実行し、例外やエラーが発生しないことを検証
        $provider->boot();

        // ここまでエラーなく到達できればテスト合格
        $this->assertTrue(true);
    }
}
