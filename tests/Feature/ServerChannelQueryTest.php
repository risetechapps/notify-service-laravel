<?php

use RiseTechApps\Notify\NotifyQuery;
use RiseTechApps\Notify\ServerChannelQuery;

it('can set tag with string', function () {
    $query = NotifyQuery::server()->sms()->tag('promo');

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('can set tag with array', function () {
    $query = NotifyQuery::server()->sms()->tag(['promo', 'welcome']);

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('can set status', function () {
    $query = NotifyQuery::server()->sms()->status('sent');

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('can set perPage', function () {
    $query = NotifyQuery::server()->sms()->perPage(50);

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('can set page', function () {
    $query = NotifyQuery::server()->sms()->page(2);

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('can set untagged', function () {
    $query = NotifyQuery::server()->sms()->untagged();

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('can chain all filters', function () {
    $query = NotifyQuery::server()->sms()
        ->tag('promo')
        ->status('sent')
        ->untagged()
        ->perPage(10)
        ->page(1);

    expect($query)->toBeInstanceOf(ServerChannelQuery::class);
});

it('can get list', function () {
    Http::fake([
        'https://notifykit.app.br/api/v1/sms*' => Http::response(['data' => [['id' => '1']]], 200),
    ]);

    $result = NotifyQuery::server()->sms()->get();

    expect($result)->toBeArray();
});

it('can get detail by id', function () {
    Http::fake([
        'https://notifykit.app.br/api/v1/sms/uuid-123' => Http::response(['id' => 'uuid-123', 'status' => 'sent'], 200),
    ]);

    $result = NotifyQuery::server()->sms('uuid-123')->get();

    expect($result)->toBe(['id' => 'uuid-123', 'status' => 'sent']);
});

it('can cancel with id', function () {
    Http::fake([
        'https://notifykit.app.br/api/v1/sms/uuid-123/cancel' => Http::response(['status' => true, 'current_status' => 'cancelled'], 200),
    ]);

    $result = NotifyQuery::server()->sms('uuid-123')->cancel();

    expect($result['status'])->toBeTrue();
    expect($result['current_status'])->toBe('cancelled');
});

it('returns error when canceling without id', function () {
    $result = NotifyQuery::server()->sms()->cancel();

    expect($result['status'])->toBeFalse();
    expect($result['http'])->toBe(0);
});

it('can preview email', function () {
    Http::fake([
        'https://notifykit.app.br/api/v1/email/uuid-123/preview' => Http::response('<html>Preview</html>', 200),
    ]);

    $result = NotifyQuery::server()->mail('uuid-123')->preview();

    expect($result)->toBe('<html>Preview</html>');
});

it('can get preview link', function () {
    Http::fake([
        'https://notifykit.app.br/api/v1/email/uuid-123/preview/link' => Http::response(['url' => 'https://preview.link'], 200),
    ]);

    $result = NotifyQuery::server()->mail('uuid-123')->previewLink();

    expect($result['url'])->toBe('https://preview.link');
});

it('can edit telegram message', function () {
    Http::fake([
        'https://notifykit.app.br/api/v1/telegram/messages/edit' => Http::response(['status' => true], 200),
    ]);

    $result = NotifyQuery::server()->telegram('uuid-123')->edit('New text');

    expect($result['status'])->toBeTrue();
});

it('can delete telegram message', function () {
    Http::fake([
        'https://notifykit.app.br/api/v1/telegram/messages/delete' => Http::response(['status' => true], 200),
    ]);

    $result = NotifyQuery::server()->telegram('uuid-123')->delete();

    expect($result['status'])->toBeTrue();
});

it('can pin telegram message', function () {
    Http::fake([
        'https://notifykit.app.br/api/v1/telegram/messages/pin' => Http::response(['status' => true], 200),
    ]);

    $result = NotifyQuery::server()->telegram('uuid-123')->pin();

    expect($result['status'])->toBeTrue();
});

it('can edit caption on telegram', function () {
    Http::fake([
        'https://notifykit.app.br/api/v1/telegram/messages/edit-caption' => Http::response(['status' => true], 200),
    ]);

    $result = NotifyQuery::server()->telegram('uuid-123')->editCaption('New caption');

    expect($result['status'])->toBeTrue();
});

it('can edit slack message', function () {
    Http::fake([
        'https://notifykit.app.br/api/v1/slack/messages/edit' => Http::response(['status' => true], 200),
    ]);

    $result = NotifyQuery::server()->slack('uuid-123')->edit('New text');

    expect($result['status'])->toBeTrue();
});

it('can delete slack message', function () {
    Http::fake([
        'https://notifykit.app.br/api/v1/slack/messages/delete' => Http::response(['status' => true], 200),
    ]);

    $result = NotifyQuery::server()->slack('uuid-123')->delete();

    expect($result['status'])->toBeTrue();
});

it('can edit discord message', function () {
    Http::fake([
        'https://notifykit.app.br/api/v1/discord/messages/edit' => Http::response(['status' => true], 200),
    ]);

    $result = NotifyQuery::server()->discord('uuid-123')->edit('New text');

    expect($result['status'])->toBeTrue();
});

it('returns error for edit without id', function () {
    $result = NotifyQuery::server()->sms()->edit('text');

    expect($result['status'])->toBeFalse();
});

it('returns error for delete without id', function () {
    $result = NotifyQuery::server()->sms()->delete();

    expect($result['status'])->toBeFalse();
});

it('config returns ServerDriverConfig', function () {
    $config = NotifyQuery::server()->sms()->config();

    expect($config)->toBeInstanceOf(\RiseTechApps\Notify\ServerDriverConfig::class);
});
