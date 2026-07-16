<?php

namespace RiseTechApps\Notify\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Exception;
use Illuminate\Notifications\Notification;

class NotifyFailedEvent
{
    use Dispatchable;

    public $notification;
    public $exception;

    public $channel;

    public function __construct(public $notifiable, Notification $notification, Exception $exception, string $channel)
    {
        $this->notification = $notification;
        $this->exception = $exception;
        $this->channel = $channel;
    }
}
