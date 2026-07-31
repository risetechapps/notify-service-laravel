<?php

use RiseTechApps\Notify\NotifyMultiBuilder;
use RiseTechApps\Notify\NotifyQuery;

it('can be instantiated with channels', function () {
    $builder = new NotifyMultiBuilder([
        ['channel' => 'sms', 'data' => ['to' => '+5511999999999', 'content' => 'Olá!']],
    ]);

    expect($builder)->toBeInstanceOf(NotifyMultiBuilder::class);
});

it('can be instantiated via NotifyQuery', function () {
    $builder = NotifyQuery::multi([
        ['channel' => 'sms', 'data' => ['to' => '+5511999999999', 'content' => 'Olá!']],
    ]);

    expect($builder)->toBeInstanceOf(NotifyMultiBuilder::class);
});

it('can be instantiated with webhook', function () {
    $builder = new NotifyMultiBuilder([], 'https://example.com/hook');

    expect($builder)->toBeInstanceOf(NotifyMultiBuilder::class);
});

it('send makes HTTP call', function () {
    Http::fake([
        'https://notifykit.app.br/api/v1/send/multi' => Http::response(['status' => true, 'data' => []], 200),
    ]);

    $result = NotifyQuery::multi([
        ['channel' => 'sms', 'data' => ['to' => '+5511999999999', 'content' => 'Teste']],
    ])->send();

    expect($result['status'])->toBeTrue();
});

it('send returns error on failure', function () {
    Http::fake([
        'https://notifykit.app.br/api/v1/send/multi' => Http::response(['error' => 'Server error'], 500),
    ]);

    $result = NotifyQuery::multi([
        ['channel' => 'sms', 'data' => ['to' => '+5511999999999', 'content' => 'Teste']],
    ])->send();

    expect($result)->toHaveKey('error');
});

it('send sends webhook_url when provided', function () {
    Http::fake(function ($request) {
        expect($request->url())->toContain('multi');
        $body = $request->data();
        expect($body)->toHaveKey('webhook_url');
        expect($body['webhook_url'])->toBe('https://example.com/hook');

        return Http::response(['status' => true], 200);
    });

    $result = NotifyQuery::multi([
        ['channel' => 'email', 'data' => ['to' => 'test@example.com', 'subject' => 'Test']],
    ], 'https://example.com/hook')->send();

    expect($result['status'])->toBeTrue();
});
