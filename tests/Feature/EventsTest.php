<?php

use Illuminate\Notifications\Notification;
use RiseTechApps\Notify\Events\NotifySendingEvent;
use RiseTechApps\Notify\Events\NotifySentEvent;
use RiseTechApps\Notify\Events\NotifyFailedEvent;
use RiseTechApps\Notify\Events\NotifyWebhookEvent;
use RiseTechApps\Notify\Events\NotifyCampaignWebhookEvent;

class DummyNotifiable
{
    public $key = 'notifiable-id';
}

class DummyNotification extends Notification
{
    public function via($notifiable): array
    {
        return ['notify.sms'];
    }
}

it('can instantiate NotifySendingEvent', function () {
    $notifiable = new DummyNotifiable;
    $notification = new DummyNotification;
    $event = new NotifySendingEvent($notifiable, $notification, 'sms');

    expect($event)->toBeInstanceOf(NotifySendingEvent::class);
    expect($event->channel)->toBe('sms');
    expect($event->notifiable->key)->toBe('notifiable-id');
});

it('can instantiate NotifySentEvent', function () {
    $notifiable = new DummyNotifiable;
    $notification = new DummyNotification;
    $event = new NotifySentEvent($notifiable, $notification, ['status' => 'sent'], 'sms');

    expect($event)->toBeInstanceOf(NotifySentEvent::class);
    expect($event->response)->toBe(['status' => 'sent']);
});

it('can instantiate NotifyFailedEvent', function () {
    $notifiable = new DummyNotifiable;
    $notification = new DummyNotification;
    $event = new NotifyFailedEvent($notifiable, $notification, new \Exception('Server error'), 'sms');

    expect($event)->toBeInstanceOf(NotifyFailedEvent::class);
    expect($event->exception->getMessage())->toBe('Server error');
});

it('can instantiate NotifyWebhookEvent', function () {
    $event = new NotifyWebhookEvent('delivered', 'notif-123', 'prov-456', 'sent', 'sms', ['extra' => 'data']);

    expect($event)->toBeInstanceOf(NotifyWebhookEvent::class);
    expect($event->status)->toBe('sent');
    expect($event->notificationId)->toBe('notif-123');
});

it('can instantiate NotifyCampaignWebhookEvent', function () {
    $event = new NotifyCampaignWebhookEvent('campaign-uuid', 'completed', ['progress' => 100]);

    expect($event)->toBeInstanceOf(NotifyCampaignWebhookEvent::class);
    expect($event->status)->toBe('completed');
    expect($event->campaignId)->toBe('campaign-uuid');
});
