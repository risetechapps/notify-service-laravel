<?php

namespace RiseTechApps\Notify;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use RiseTechApps\Notify\Channel;
use RiseTechApps\Notify\Http\Middleware\VerifyNotifySignature;

class NotifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerPublishes();
        $this->registerChannels();
        $this->registerRoutes();
    }

    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'notify');

        $this->app->singleton(Notify::class, fn() => new Notify());
        $this->app->singleton(NotifyQuery::class, fn() => new NotifyQuery());
    }

    protected function registerChannels(): void
    {
        Notification::extend('notify.sms',       fn() => new Channel\NotifyChannelSms());
        Notification::extend('notify.mail',      fn() => new Channel\NotifyChannelMail());
        Notification::extend('notify.push',      fn() => new Channel\NotifyChannelPush());
        Notification::extend('notify.apns',      fn() => new Channel\NotifyChannelApns());
        Notification::extend('notify.telegram',  fn() => new Channel\NotifyChannelTelegram());
        Notification::extend('notify.slack',     fn() => new Channel\NotifyChannelSlack());
        Notification::extend('notify.discord',   fn() => new Channel\NotifyChannelDiscord());
        Notification::extend('notify.teams',     fn() => new Channel\NotifyChannelTeams());
        Notification::extend('notify.websocket', fn() => new Channel\NotifyChannelWebSocket());
        Notification::extend('notify.webhook',   fn() => new Channel\NotifyChannelWebhook());
    }

    protected function registerRoutes(): void
    {
        // Alias para quem registra as rotas manualmente:
        // Route::post(...)->middleware('notify.signature');
        $this->app['router']->aliasMiddleware('notify.signature', VerifyNotifySignature::class);

        if (!config('notify.routes', true)) {
            return;
        }

        $middleware = (array)config('notify.routes_middleware', ['api']);

        // A validação de assinatura é sempre aplicada; ela mesma se desliga
        // quando notify.webhook_verify é false.
        $middleware[] = VerifyNotifySignature::class;

        Route::group([
            'prefix'     => config('notify.routes_prefix', 'notify'),
            'middleware' => $middleware,
            'as'         => 'notify.',
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        });
    }

    protected function registerPublishes(): void
    {
        // O pacote não persiste nada localmente — não há migrations a carregar.
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/config.php' => config_path('notify.php'),
            ], 'config');
        }
    }
}
