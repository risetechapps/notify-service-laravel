<?php

namespace RiseTechApps\Notify\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Exception;
use Illuminate\Notifications\Notification;

class NotifyFailedEvent
{
    use Dispatchable;

    public function __construct(
        public $notifiable,
        public Notification $notification,
        public Exception $exception,
        public string $channel,
    ) {}
}
