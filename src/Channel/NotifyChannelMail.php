<?php

namespace RiseTechApps\Notify\Channel;

use Exception;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RiseTechApps\Notify\Events\NotifyFailedEvent;
use RiseTechApps\Notify\Events\NotifySendingEvent;
use RiseTechApps\Notify\Events\NotifySentEvent;
use RiseTechApps\Notify\Message\NotifyMail;

class NotifyChannelMail extends NotifyChannel
{
    public function send($notifiable, Notification $notification)
    {
        try {
            if (!$to = $notifiable->routeNotificationFor('mail', $notification)) {
                return null;
            }

            $message = $notification->toNotifyMail($notifiable);

            if (!$message instanceof NotifyMail) {
                return null;
            }

            // routeNotificationFor('mail') pode devolver "email" ou ["email" => "Nome"].
            if (is_array($to)) {
                $email = array_key_first($to);
                $name  = is_string($to[$email] ?? null) ? $to[$email] : '';
                $message->to($email, $name);
            } else {
                $message->to($to);
            }

            Event::dispatch(new NotifySendingEvent($notifiable, $notification, 'mail'));

            $data = $message->toArray();

            if (($data['webhook_url'] ?? null) === null) {
                $data['webhook_url'] = config('notify.webhook');
            }

            $response = Http::withHeaders(['X-API-KEY' => $this->apiKey()])
                ->acceptJson()
                ->post("{$this->apiUrl}/api/v1/send/mail", $data);

            if ($response->failed()) {
                $errorBody = $response->json() ?: ['error' => $response->body()];

                Event::dispatch(new NotifyFailedEvent($notifiable, $notification, new Exception($response->body()), 'mail'));

                \Illuminate\Support\Facades\Log::error('Error by sending notification', [
                    'notifiable' => $notifiable,
                    'notification' => $notification,
                    'response' => $errorBody,
                ]);

                return $errorBody;
            }

            $responseJson = $response->json();

            Event::dispatch(new NotifySentEvent($notifiable, $notification, $responseJson, 'mail'));

            \Illuminate\Support\Facades\Log::info('Notification sent', [
                'notifiable' => $notifiable,
                'notification' => $notification,
                'response' => $responseJson,
            ]);

            return $responseJson;
        } catch (\Exception $exception) {
            Event::dispatch(new NotifyFailedEvent($notifiable, $notification, $exception, 'mail'));

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
