<?php

namespace RiseTechApps\Notify;

use Illuminate\Support\Facades\Http;

class ServerChannelQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected string $resource,
        protected ?string $id = null,
    ) {}

    public function tag(string|array $tag): static
    {
        $this->params['tag'] = is_array($tag) ? array_values($tag) : $tag;
        return $this;
    }

    public function untagged(bool $untagged = true): static
    {
        $this->params['untagged'] = $untagged;
        return $this;
    }

    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    public function perPage(int $perPage): static
    {
        $this->params['per_page'] = $perPage;
        return $this;
    }

    public function page(int $page): static
    {
        $this->params['page'] = $page;
        return $this;
    }

    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/{$this->resource}/{$this->id}")->json() ?? [];
        }

        return $this->http()->get("/api/v1/{$this->resource}", $this->params)->json() ?? [];
    }

    public function cancel(?string $id = null): array
    {
        $id ??= $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => ucfirst($this->resource) . ' id is required to cancel.', 'notification_id' => null, 'current_status' => null, 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/{$this->resource}/{$id}/cancel");

        return [
            'status'          => $response->json('status', false),
            'message'         => $response->json('message'),
            'notification_id' => $response->json('notification_id'),
            'current_status'  => $response->json('current_status'),
            'http'            => $response->status(),
        ];
    }

    public function config(?string $id = null): ServerDriverConfig
    {
        return new ServerDriverConfig($this->apiUrl, $this->apiKey, $this->resource, $id);
    }

    public function edit(string $text, ?string $parseMode = null): array
    {
        $id = $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => ucfirst($this->resource) . ' id is required to edit.', 'http' => 0];
        }

        $payload = ['notification_id' => $id, 'text' => $text];

        if ($parseMode !== null) {
            $payload['parse_mode'] = $parseMode;
        }

        $response = $this->http()->post("/api/v1/{$this->resource}/messages/edit", $payload);

        return [
            'status'  => $response->json('status', false),
            'message' => $response->json('message'),
            'http'    => $response->status(),
        ];
    }

    public function delete(): array
    {
        $id = $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => ucfirst($this->resource) . ' id is required to delete.', 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/{$this->resource}/messages/delete", [
            'notification_id' => $id,
        ]);

        return [
            'status'  => $response->json('status', false),
            'message' => $response->json('message'),
            'http'    => $response->status(),
        ];
    }

    public function editCaption(string $caption): array
    {
        $id = $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => ucfirst($this->resource) . ' id is required to edit caption.', 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/{$this->resource}/messages/edit-caption", [
            'notification_id' => $id,
            'caption'         => $caption,
        ]);

        return [
            'status'  => $response->json('status', false),
            'message' => $response->json('message'),
            'http'    => $response->status(),
        ];
    }

    public function pin(bool $disableNotification = false): array
    {
        $id = $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => ucfirst($this->resource) . ' id is required to pin.', 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/{$this->resource}/messages/pin", [
            'notification_id'       => $id,
            'disable_notification'  => $disableNotification,
        ]);

        return [
            'status'  => $response->json('status', false),
            'message' => $response->json('message'),
            'http'    => $response->status(),
        ];
    }

    public function preview(?string $format = null): string|array
    {
        $id = $this->id;

        if ($id === null) {
            return ['error' => ucfirst($this->resource) . ' id is required for preview.'];
        }

        $query = $format !== null ? ['format' => $format] : [];

        $response = $this->http()->get("/api/v1/{$this->resource}/{$id}/preview", $query);

        if ($response->failed()) {
            return ['error' => $response->body()];
        }

        if ($format === 'json') {
            return $response->json() ?? [];
        }

        return $response->body();
    }

    public function previewLink(): array
    {
        $id = $this->id;

        if ($id === null) {
            return ['error' => ucfirst($this->resource) . ' id is required for preview link.'];
        }

        $response = $this->http()->get("/api/v1/{$this->resource}/{$id}/preview/link");

        if ($response->failed()) {
            return ['error' => $response->body()];
        }

        return $response->json() ?? [];
    }

    protected function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}
