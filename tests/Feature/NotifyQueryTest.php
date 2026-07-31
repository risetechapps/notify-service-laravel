<?php

use RiseTechApps\Notify\NotifyMultiBuilder;
use RiseTechApps\Notify\NotifyQuery;
use RiseTechApps\Notify\ServerChannelQuery;
use RiseTechApps\Notify\ServerQuery;

it('can be instantiated', function () {
    $query = new NotifyQuery();

    expect($query)->toBeInstanceOf(NotifyQuery::class);
});

it('server returns ServerQuery instance', function () {
    $server = NotifyQuery::server();

    expect($server)->toBeInstanceOf(ServerQuery::class);
});

it('multi returns NotifyMultiBuilder instance', function () {
    $multi = NotifyQuery::multi([
        ['channel' => 'sms', 'data' => ['to' => '+5511999999999', 'content' => 'Olá!']],
    ]);

    expect($multi)->toBeInstanceOf(NotifyMultiBuilder::class);
});

it('multi returns NotifyMultiBuilder with webhook', function () {
    $multi = NotifyQuery::multi([], 'https://example.com/webhook');

    expect($multi)->toBeInstanceOf(NotifyMultiBuilder::class);
});

it('server sms returns ServerChannelQuery', function () {
    $query = NotifyQuery::server()->sms();

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('server sms with id returns ServerChannelQuery', function () {
    $query = NotifyQuery::server()->sms('some-uuid');

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('server mail returns ServerChannelQuery', function () {
    $query = NotifyQuery::server()->mail();

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('server push returns ServerChannelQuery', function () {
    $query = NotifyQuery::server()->push();

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('server apns returns ServerChannelQuery', function () {
    $query = NotifyQuery::server()->apns();

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('server telegram returns ServerChannelQuery', function () {
    $query = NotifyQuery::server()->telegram();

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('server slack returns ServerChannelQuery', function () {
    $query = NotifyQuery::server()->slack();

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('server discord returns ServerChannelQuery', function () {
    $query = NotifyQuery::server()->discord();

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('server teams returns ServerChannelQuery', function () {
    $query = NotifyQuery::server()->teams();

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('server websocket returns ServerChannelQuery', function () {
    $query = NotifyQuery::server()->websocket();

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('server webhook returns ServerChannelQuery', function () {
    $query = NotifyQuery::server()->webhook();

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('server notifications returns ServerNotificationQuery', function () {
    $query = NotifyQuery::server()->notifications();

    expect($query)->toBeInstanceOf(\RiseTechApps\Notify\ServerNotificationQuery::class);
});

it('server campaigns returns ServerCampaignQuery', function () {
    $query = NotifyQuery::server()->campaigns('sms');

    expect($query)->toBeInstanceOf(\RiseTechApps\Notify\ServerCampaignQuery::class);
});

it('server drivers returns array', function () {
    Http::fake([
        'https://notifykit.app.br/api/v1/configurations/drivers' => Http::response(['data' => [['driver' => 'twilio']]], 200),
    ]);

    $result = NotifyQuery::server()->drivers();

    expect($result)->toBeArray();
});

it('server configurations returns array', function () {
    Http::fake([
        'https://notifykit.app.br/api/v1/configurations*' => Http::response(['data' => [['id' => '1']]], 200),
    ]);

    $result = NotifyQuery::server()->configurations('sms');

    expect($result)->toBeArray();
});

it('server notification returns data', function () {
    Http::fake([
        'https://notifykit.app.br/api/v1/notifications/uuid-123' => Http::response(['data' => ['id' => 'uuid-123', 'status' => 'sent']], 200),
    ]);

    $result = NotifyQuery::server()->notification('uuid-123');

    expect($result)->toBe(['id' => 'uuid-123', 'status' => 'sent']);
});

it('server notificationEvents returns array', function () {
    Http::fake([
        'https://notifykit.app.br/api/v1/notifications/uuid-123/events' => Http::response(['current_status' => 'sent', 'events' => []], 200),
    ]);

    $result = NotifyQuery::server()->notificationEvents('uuid-123');

    expect($result)->toHaveKey('current_status');
});

it('notification query can filter by search', function () {
    $query = NotifyQuery::server()->notifications()->search('uuid-123');

    expect($query)->toBeInstanceOf(\RiseTechApps\Notify\ServerNotificationQuery::class);
});

it('notification query can filter by tag', function () {
    $query = NotifyQuery::server()->notifications()->tag('vip');

    expect($query)->toBeInstanceOf(\RiseTechApps\Notify\ServerNotificationQuery::class);
});

it('notification query can filter by untagged', function () {
    $query = NotifyQuery::server()->notifications()->untagged();

    expect($query)->toBeInstanceOf(\RiseTechApps\Notify\ServerNotificationQuery::class);
});

it('notification get sends search param', function () {
    Http::fake();

    NotifyQuery::server()->notifications()->search('uuid-123')->get();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'search=uuid-123');
    });
});
