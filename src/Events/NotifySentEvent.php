<?php

namespace RiseTechApps\Notify\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\Notification;

class NotifySentEvent
{
    use Dispatchable;

    public function __construct(
        public $notifiable,
        public Notification $notification,
        public $response,
        public string $channel,
    ) {}
}
