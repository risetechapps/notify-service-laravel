<?php

namespace RiseTechApps\Notify;

use Illuminate\Support\Facades\Http;

class NotifyMultiBuilder
{
    public function __construct(
        protected array $channels,
        protected ?string $webhookUrl = null,
    ) {}

    public function send(): array
    {
        try {
            $payload = array_filter([
                'channels' => $this->channels,
                'webhook_url' => $this->webhookUrl,
            ], fn($v) => $v !== null);

            $response = Http::withHeaders(['X-API-KEY' => config('notify.key')])
                ->acceptJson()
                ->post(Notify::BASE_URL . '/api/v1/send/multi', $payload);

            if ($response->failed()) {
                return $response->json() ?: ['error' => $response->body()];
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            report($e);

            return ['error' => $e->getMessage()];
        }
    }
}
