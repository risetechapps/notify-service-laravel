<?php

namespace RiseTechApps\Notify\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\Notification;


class NotifySendingEvent
{
    use Dispatchable;

    public $notification;

    public $channel;

    public function __construct(public $notifiable, Notification $notification, string $channel)
    {
        $this->notification = $notification;
        $this->channel = $channel;
    }
}
