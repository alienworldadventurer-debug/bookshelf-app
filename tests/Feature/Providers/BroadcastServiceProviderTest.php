<?php

namespace Tests\Feature\Providers;

use App\Providers\BroadcastServiceProvider;
use Tests\TestCase;

class BroadcastServiceProviderTest extends TestCase
{
    /**
     * BroadcastServiceProviderのboot処理が例外なく完了することを検証する。
     *
     * @return void サービスプロバイダーが正常に起動することを確認する
     */
    public function test_broadcast_service_provider_boots_successfully(): void
    {
        $provider = new BroadcastServiceProvider($this->app);

        $provider->boot();

        $this->assertTrue(true);
    }
}
