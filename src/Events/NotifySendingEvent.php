<?php

namespace RiseTechApps\Notify\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\Notification;


class NotifySendingEvent
{
    use Dispatchable;

    public function __construct(
        public $notifiable,
        public Notification $notification,
        public string $channel,
    ) {}
}
