<?php

namespace RiseTechApps\Notify\Channel;

use Exception;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RiseTechApps\Notify\Events\NotifyFailedEvent;
use RiseTechApps\Notify\Events\NotifySendingEvent;
use RiseTechApps\Notify\Events\NotifySentEvent;
use RiseTechApps\Notify\Message\NotifyWebhook;

class NotifyChannelWebhook extends NotifyChannel
{
    public function send($notifiable, Notification $notification)
    {
        try {
            $message = $notification->toNotifyWebhook($notifiable);

            if (!$message instanceof NotifyWebhook) {
                return null;
            }

            Event::dispatch(new NotifySendingEvent($notifiable, $notification, 'webhook'));

            $data = $message->toArray();

            if (($data['webhook_url'] ?? null) === null) {
                $data['webhook_url'] = config('notify.webhook');
            }

            $response = Http::withHeaders(['X-API-KEY' => $this->apiKey()])
                ->acceptJson()
                ->post("{$this->apiUrl}/api/v1/send/webhook", $data);

            if ($response->failed()) {
                $errorBody = $response->json() ?: ['error' => $response->body()];

                Event::dispatch(new NotifyFailedEvent($notifiable, $notification, new Exception($response->body()), 'webhook'));

                \Illuminate\Support\Facades\Log::error('Error by sending notification', [
                    'notifiable' => $notifiable,
                    'notification' => $notification,
                    'response' => $errorBody,
                ]);

                return $errorBody;
            }

            $responseJson = $response->json();

            Event::dispatch(new NotifySentEvent($notifiable, $notification, $responseJson, 'webhook'));

            \Illuminate\Support\Facades\Log::info('Notification sent', [
                'notifiable' => $notifiable,
                'notification' => $notification,
                'response' => $responseJson,
            ]);

            return $responseJson;
        } catch (\Exception $exception) {
            Event::dispatch(new NotifyFailedEvent($notifiable, $notification, $exception, 'webhook'));

            \Illuminate\Support\Facades\Log::error('Error by sending notification', [
                'notifiable' => $notifiable,
                'notification' => $notification,
                'exception' => $exception,
            ]);

            report($exception);

            return ['error' => $exception->getMessage()];
        }
    }
}
