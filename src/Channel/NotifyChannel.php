<?php

namespace RiseTechApps\Notify\Channel;

use Illuminate\Notifications\Notification;

abstract class NotifyChannel
{
    protected string $apiUrl;
    protected ?string $apiKey = null;

    public function __construct()
    {
        $this->apiUrl = \RiseTechApps\Notify\Notify::BASE_URL;
    }

    protected function apiKey(): string
    {
        if ($this->apiKey === null) {
            $this->apiKey = config('notify.key', '');
        }

        return $this->apiKey;
    }

    abstract public function send($notifiable, Notification $notification);
}
