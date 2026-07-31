<?php

namespace RiseTechApps\Notify\Channel;

use Exception;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RiseTechApps\Notify\Events\NotifyFailedEvent;
use RiseTechApps\Notify\Events\NotifySendingEvent;
use RiseTechApps\Notify\Events\NotifySentEvent;
use RiseTechApps\Notify\Message\NotifyApns;

class NotifyChannelApns extends NotifyChannel
{
    public function send($notifiable, Notification $notification)
    {
        try {
            $message = $notification->toNotifyApns($notifiable);

            if (!$message instanceof NotifyApns) {
                return null;
            }

            if (!$message->token && $to = $notifiable->routeNotificationFor('apns', $notification)) {
                $message->token($to);
            }

            Event::dispatch(new NotifySendingEvent($notifiable, $notification, 'apns'));

            $data = $message->toArray();

            if (($data['webhook_url'] ?? null) === null) {
                $data['webhook_url'] = config('notify.webhook');
            }

            $response = Http::withHeaders(['X-API-KEY' => $this->apiKey()])
                ->acceptJson()
                ->post("{$this->apiUrl}/api/v1/send/apns", $data);

            if ($response->failed()) {
                $errorBody = $response->json() ?: ['error' => $response->body()];

                Event::dispatch(new NotifyFailedEvent($notifiable, $notification, new Exception($response->body()), 'apns'));

                \Illuminate\Support\Facades\Log::error('Error by sending notification', [
                    'notifiable' => $notifiable,
                    'notification' => $notification,
                    'response' => $errorBody,
                ]);

                return $errorBody;
            }

            $responseJson = $response->json();

            Event::dispatch(new NotifySentEvent($notifiable, $notification, $responseJson, 'apns'));

            \Illuminate\Support\Facades\Log::info('Notification sent', [
                'notifiable' => $notifiable,
                'notification' => $notification,
                'response' => $responseJson,
            ]);

            return $responseJson;
        } catch (\Exception $exception) {
            Event::dispatch(new NotifyFailedEvent($notifiable, $notification, $exception, 'apns'));

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
