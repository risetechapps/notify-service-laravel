<?php

namespace RiseTechApps\Notify\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Valida a assinatura HMAC dos callbacks enviados pelo servidor de notificações.
 *
 * O servidor assina o material "{timestamp}.{corpo bruto}" com HMAC-SHA256 e envia
 * o resultado no header:
 *
 *   X-Notify-Signature: t=1750000000,v1=9f86d081884c7d65...
 *
 * A validação usa o corpo BRUTO da requisição ($request->getContent()). Nunca
 * reserialize o JSON (json_encode($request->all())) — a ordem das chaves e o escape
 * de "/" e unicode mudam os bytes e a assinatura nunca confere.
 *
 * Configuração:
 *   notify.webhook_secret     segredo compartilhado com o servidor (NOTIFY_SERVICE_WEBHOOK_SECRET)
 *   notify.webhook_verify     true (default) valida; false desliga a validação
 *   notify.webhook_tolerance  janela anti-replay em segundos (default 300)
 */
class VerifyNotifySignature
{
    public const HEADER = 'X-Notify-Signature';

    public function handle(Request $request, Closure $next): Response
    {
        if (!config('notify.webhook_verify', true)) {
            return $next($request);
        }

        $secret = (string)config('notify.webhook_secret', '');

        if ($secret === '') {
            Log::error('Notify: webhook recebido mas notify.webhook_secret não está configurado. Defina NOTIFY_SERVICE_WEBHOOK_SECRET ou desligue a validação com notify.webhook_verify=false.');

            return $this->deny('Webhook secret not configured');
        }

        $header = $request->header(self::HEADER);

        if (!is_string($header) || $header === '') {
            return $this->deny('Missing signature header');
        }

        [$timestamp, $signatures] = $this->parse($header);

        if ($timestamp === null || $signatures === []) {
            return $this->deny('Malformed signature header');
        }

        $tolerance = (int)config('notify.webhook_tolerance', 300);

        if ($tolerance > 0 && abs(time() - $timestamp) > $tolerance) {
            return $this->deny('Signature timestamp outside tolerance');
        }

        // Corpo bruto — não reserializar.
        $expected = hash_hmac('sha256', $timestamp . '.' . $request->getContent(), $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return $next($request);
            }
        }

        return $this->deny('Invalid signature');
    }

    /**
     * Extrai o timestamp e as assinaturas v1 do header.
     *
     * Aceita mais de um v1 no mesmo header (útil durante rotação de segredo):
     * "t=1750000000,v1=abc...,v1=def...".
     *
     * @return array{0: int|null, 1: array<int, string>}
     */
    protected function parse(string $header): array
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);

            if (count($pair) !== 2) {
                continue;
            }

            [$key, $value] = [trim($pair[0]), trim($pair[1])];

            if ($key === 't' && preg_match('/^\d+$/', $value)) {
                $timestamp = (int)$value;
                continue;
            }

            if ($key === 'v1' && preg_match('/^[0-9a-fA-F]{64}$/', $value)) {
                $signatures[] = strtolower($value);
            }
        }

        return [$timestamp, $signatures];
    }

    protected function deny(string $reason): JsonResponse
    {
        return new JsonResponse(['message' => $reason], 403);
    }
}
