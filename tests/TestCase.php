<?php

namespace RiseTechApps\Notify\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RiseTechApps\Notify\NotifyServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make('config')->set('notify.key', 'test-api-key');
        $this->app->make('config')->set('notify.routes', false);
    }

    public function createApplication(): Application
    {
        $app = new Application(dirname(__DIR__));

        $app->singleton(Kernel::class, \Illuminate\Foundation\Console\Kernel::class);

        $app->singleton(
            \Illuminate\Contracts\Debug\ExceptionHandler::class,
            \Illuminate\Foundation\Exceptions\Handler::class
        );

        $app->make(Kernel::class)->bootstrap();

        $app->register(NotifyServiceProvider::class);
        $app->boot();

        return $app;
    }
}
