<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * アプリケーションのイベントとリスナーを登録する。
     */
    public function boot(): void {}

    /**
     * イベントとリスナーの自動検出を無効にする。
     *
     * @return bool 自動検出を有効にする場合はtrue
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
