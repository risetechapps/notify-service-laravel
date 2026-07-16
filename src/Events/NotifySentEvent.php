<?php

namespace RiseTechApps\Notify\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\Notification;

class NotifySentEvent
{
    use Dispatchable;

    public $notification;
    public $channel;

    public function __construct(public $notifiable, Notification $notification, public $response, string $channel)
    {
        $this->notification = $notification;
        $this->response = $response;
        $this->channel = $channel;
    }
}
