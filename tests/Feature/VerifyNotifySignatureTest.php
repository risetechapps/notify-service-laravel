<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RiseTechApps\Notify\Http\Middleware\VerifyNotifySignature;

/**
 * Monta uma requisição de webhook com o corpo bruto informado.
 */
function webhookRequest(string $body, ?string $signature): Request
{
    $server = ['CONTENT_TYPE' => 'application/json'];

    if ($signature !== null) {
        $server['HTTP_X_NOTIFY_SIGNATURE'] = $signature;
    }

    return Request::create('/notify/webhook', 'POST', [], [], [], $server, $body);
}

function signHeader(string $body, string $secret, ?int $timestamp = null): string
{
    $timestamp ??= time();

    return sprintf('t=%d,v1=%s', $timestamp, hash_hmac('sha256', $timestamp . '.' . $body, $secret));
}

function runMiddleware(Request $request): \Symfony\Component\HttpFoundation\Response
{
    return (new VerifyNotifySignature)->handle($request, fn() => new JsonResponse(['ok' => true]));
}

beforeEach(function () {
    config()->set('notify.webhook_secret', 'segredo-de-teste');
    config()->set('notify.webhook_verify', true);
    config()->set('notify.webhook_tolerance', 300);
});

it('accepts a request with a valid signature', function () {
    $body = '{"event":"delivered","notification_id":"uuid-1"}';

    $response = runMiddleware(webhookRequest($body, signHeader($body, 'segredo-de-teste')));

    expect($response->getStatusCode())->toBe(200);
});

it('rejects a signature generated with the wrong secret', function () {
    $body = '{"event":"delivered"}';

    $response = runMiddleware(webhookRequest($body, signHeader($body, 'outro-segredo')));

    expect($response->getStatusCode())->toBe(403);
});

it('rejects a tampered body', function () {
    $body = '{"event":"delivered"}';
    $header = signHeader($body, 'segredo-de-teste');

    $response = runMiddleware(webhookRequest('{"event":"error"}', $header));

    expect($response->getStatusCode())->toBe(403);
});

it('rejects a replayed payload outside the tolerance window', function () {
    $body = '{"event":"delivered"}';
    $header = signHeader($body, 'segredo-de-teste', time() - 600);

    $response = runMiddleware(webhookRequest($body, $header));

    expect($response->getStatusCode())->toBe(403);
});

it('accepts an old payload when tolerance is disabled', function () {
    config()->set('notify.webhook_tolerance', 0);

    $body = '{"event":"delivered"}';
    $header = signHeader($body, 'segredo-de-teste', time() - 6000);

    expect(runMiddleware(webhookRequest($body, $header))->getStatusCode())->toBe(200);
});

it('rejects a request without the signature header', function () {
    expect(runMiddleware(webhookRequest('{}', null))->getStatusCode())->toBe(403);
});

it('rejects a malformed signature header', function () {
    expect(runMiddleware(webhookRequest('{}', 'v1=abc'))->getStatusCode())->toBe(403);
    expect(runMiddleware(webhookRequest('{}', 't=123'))->getStatusCode())->toBe(403);
    expect(runMiddleware(webhookRequest('{}', 'lixo'))->getStatusCode())->toBe(403);
});

it('rejects everything when no secret is configured', function () {
    config()->set('notify.webhook_secret', '');

    $body = '{"event":"delivered"}';

    expect(runMiddleware(webhookRequest($body, signHeader($body, '')))->getStatusCode())->toBe(403);
});

it('skips validation when webhook_verify is false', function () {
    config()->set('notify.webhook_verify', false);

    expect(runMiddleware(webhookRequest('{}', null))->getStatusCode())->toBe(200);
});

it('accepts any of the signatures during secret rotation', function () {
    $body = '{"event":"delivered"}';
    $timestamp = time();

    $header = sprintf(
        't=%d,v1=%s,v1=%s',
        $timestamp,
        hash_hmac('sha256', $timestamp . '.' . $body, 'segredo-antigo'),
        hash_hmac('sha256', $timestamp . '.' . $body, 'segredo-de-teste'),
    );

    expect(runMiddleware(webhookRequest($body, $header))->getStatusCode())->toBe(200);
});

it('does not reserialize the body when validating', function () {
    // Chaves fora de ordem e "/" sem escape: json_encode($request->all()) mudaria
    // os bytes e quebraria a assinatura. O middleware deve usar o corpo bruto.
    $body = '{"z":"a/b","a":"acentuação"}';

    expect(runMiddleware(webhookRequest($body, signHeader($body, 'segredo-de-teste')))->getStatusCode())->toBe(200);
});
