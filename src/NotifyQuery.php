<?php

namespace RiseTechApps\Notify;

use Illuminate\Support\Facades\Http;

/**
 * Consultas de notificações e campanhas no servidor (tempo real via API).
 *
 * O pacote não persiste nada localmente — todas as consultas vão ao servidor.
 *
 *   // Notificações
 *   NotifyQuery::server()->notifications()->channel('sms')->status('send')->get();
 *   NotifyQuery::server()->notifications()->from('2026-01-01')->to('2026-03-31')->get();
 *   NotifyQuery::server()->notification('server-uuid');           // detalhes + eventos
 *   NotifyQuery::server()->notificationEvents('server-uuid');     // só a timeline
 *
 *   // SMS
 *   NotifyQuery::server()->sms()->tag('promo')->status('delivered')->get();  // listar
 *   NotifyQuery::server()->sms('server-uuid')->get();                        // detalhe + timeline
 *   NotifyQuery::server()->sms('server-uuid')->cancel();                     // cancelar (se created)
 *
 *   // E-mail
 *   NotifyQuery::server()->mail()->tag('pedido')->status('delivered')->get(); // listar
 *   NotifyQuery::server()->mail('server-uuid')->get();                        // detalhe + timeline
 *   NotifyQuery::server()->mail('server-uuid')->cancel();                     // cancelar (se created)
 *
 *   // Campanhas (endpoints por canal: sms | mail)
 *   NotifyQuery::server()->campaigns('sms')->status('processing')->get();   // listar
 *   NotifyQuery::server()->campaigns('mail', 'campaign-uuid')->get();        // detalhe + progresso
 *   NotifyQuery::server()->campaigns('sms', 'campaign-uuid')->cancel();      // cancelar
 *   NotifyQuery::server()->campaigns('mail', 'uuid')->contacts()->status('failed')->get(); // contatos
 */
class NotifyQuery
{
    /** Ponto de entrada para consultas no servidor. */
    public static function server(): ServerQuery
    {
        return new ServerQuery();
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Fluent builder para consultas na API do servidor.
 * Instanciado via NotifyQuery::server().
 */
class ServerQuery
{
    protected string $apiUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->apiUrl = Notify::BASE_URL;
        $this->apiKey = config('notify.key', '');
    }

    // ── Notificações ──────────────────────────────────────────────────────────

    /** Inicia query de notificações individuais no servidor. */
    public function notifications(): ServerNotificationQuery
    {
        return new ServerNotificationQuery($this->apiUrl, $this->apiKey);
    }

    /**
     * Busca detalhes + eventos de uma notificação no servidor.
     *
     * @param  string $serverNotificationId  UUID retornado pelo servidor no dispatch
     * @return array{data: array, events: array}
     */
    public function notification(string $serverNotificationId): array
    {
        $response = $this->http()->get("/api/v1/notifications/{$serverNotificationId}");
        return $response->json('data', []);
    }

    /**
     * Busca apenas a timeline de eventos de uma notificação.
     *
     * @param  string $serverNotificationId
     * @return array{current_status: string, events: array}
     */
    public function notificationEvents(string $serverNotificationId): array
    {
        $response = $this->http()->get("/api/v1/notifications/{$serverNotificationId}/events");
        return $response->json() ?? [];
    }

    // ── SMS ─────────────────────────────────────────────────────────────────

    /**
     * Recurso de SMS no servidor (listagem, detalhe e ações).
     *
     *   // Listar (filtra por tag/status)
     *   NotifyQuery::server()->sms()->tag('promo')->status('delivered')->get();
     *
     *   // Detalhe + timeline de um SMS
     *   NotifyQuery::server()->sms('uuid')->get();
     *
     *   // Cancelar (só enquanto `created`)
     *   NotifyQuery::server()->sms('uuid')->cancel();
     *   NotifyQuery::server()->sms()->cancel('uuid');
     *
     * @param string|null $id  UUID do SMS, para detalhe/ações sobre um registro específico.
     */
    public function sms(?string $id = null): ServerSmsQuery
    {
        return new ServerSmsQuery($this->apiUrl, $this->apiKey, $id);
    }

    // ── E-mail ────────────────────────────────────────────────────────────────

    /**
     * Recurso de e-mail no servidor (listagem, detalhe e ações).
     *
     *   // Listar (filtra por tag/status)
     *   NotifyQuery::server()->mail()->tag('pedido')->status('delivered')->get();
     *
     *   // Detalhe + timeline de um e-mail
     *   NotifyQuery::server()->mail('uuid')->get();
     *
     *   // Cancelar (só enquanto `created`)
     *   NotifyQuery::server()->mail('uuid')->cancel();
     *   NotifyQuery::server()->mail()->cancel('uuid');
     *
     * @param string|null $id  UUID do e-mail, para detalhe/ações sobre um registro específico.
     */
    public function mail(?string $id = null): ServerMailQuery
    {
        return new ServerMailQuery($this->apiUrl, $this->apiKey, $id);
    }

    // ── Push ──────────────────────────────────────────────────────────────────

    /**
     * Recurso de push (FCM) no servidor (listagem, detalhe e ações).
     *
     *   // Listar (filtra por tag/status)
     *   NotifyQuery::server()->push()->tag('promo')->status('sent')->get();
     *
     *   // Detalhe + timeline de um push
     *   NotifyQuery::server()->push('uuid')->get();
     *
     *   // Cancelar (só enquanto `created`)
     *   NotifyQuery::server()->push('uuid')->cancel();
     *   NotifyQuery::server()->push()->cancel('uuid');
     *
     * @param string|null $id  UUID do push, para detalhe/ações sobre um registro específico.
     */
    public function push(?string $id = null): ServerPushQuery
    {
        return new ServerPushQuery($this->apiUrl, $this->apiKey, $id);
    }

    // ── APNs ────────────────────────────────────────────────────────────────────

    /**
     * Recurso de push APNs (Apple) no servidor (listagem, detalhe e ações).
     *
     *   // Listar (filtra por tag/status)
     *   NotifyQuery::server()->apns()->tag('entregas')->status('sent')->get();
     *
     *   // Detalhe + timeline de um push APNs
     *   NotifyQuery::server()->apns('uuid')->get();
     *
     *   // Cancelar (só enquanto `created`)
     *   NotifyQuery::server()->apns('uuid')->cancel();
     *   NotifyQuery::server()->apns()->cancel('uuid');
     *
     * @param string|null $id  UUID do envio, para detalhe/ações sobre um registro específico.
     */
    public function apns(?string $id = null): ServerApnsQuery
    {
        return new ServerApnsQuery($this->apiUrl, $this->apiKey, $id);
    }

    // ── Telegram ────────────────────────────────────────────────────────────────

    /**
     * Recurso de Telegram no servidor (listagem, detalhe e ações).
     *
     *   // Listar (filtra por tag/status)
     *   NotifyQuery::server()->telegram()->tag('pedidos')->status('sent')->get();
     *
     *   // Detalhe + timeline de um envio
     *   NotifyQuery::server()->telegram('uuid')->get();
     *
     *   // Cancelar (só enquanto `created`)
     *   NotifyQuery::server()->telegram('uuid')->cancel();
     *   NotifyQuery::server()->telegram()->cancel('uuid');
     *
     *   // Gerenciar a mensagem já enviada (só precisa do notification_id do recurso)
     *   NotifyQuery::server()->telegram('uuid')->edit('novo texto');
     *   NotifyQuery::server()->telegram('uuid')->editCaption('nova legenda');
     *   NotifyQuery::server()->telegram('uuid')->delete();
     *   NotifyQuery::server()->telegram('uuid')->pin(disableNotification: true);
     *
     * @param string|null $id  UUID do envio, para detalhe/ações sobre um registro específico.
     */
    public function telegram(?string $id = null): ServerTelegramQuery
    {
        return new ServerTelegramQuery($this->apiUrl, $this->apiKey, $id);
    }

    // ── Slack ─────────────────────────────────────────────────────────────────

    /**
     * Recurso de Slack no servidor (listagem, detalhe e ações).
     *
     *   // Listar (filtra por tag/status)
     *   NotifyQuery::server()->slack()->tag('deploys')->status('sent')->get();
     *
     *   // Detalhe + timeline de um envio
     *   NotifyQuery::server()->slack('uuid')->get();
     *
     *   // Cancelar (só enquanto `created`)
     *   NotifyQuery::server()->slack('uuid')->cancel();
     *   NotifyQuery::server()->slack()->cancel('uuid');
     *
     *   // Gerenciar a mensagem já enviada (só Bot Token)
     *   NotifyQuery::server()->slack('uuid')->edit('novo texto');
     *   NotifyQuery::server()->slack('uuid')->delete();
     *
     * @param string|null $id  UUID do envio, para detalhe/ações sobre um registro específico.
     */
    public function slack(?string $id = null): ServerSlackQuery
    {
        return new ServerSlackQuery($this->apiUrl, $this->apiKey, $id);
    }

    // ── Discord ───────────────────────────────────────────────────────────────

    /**
     * Recurso de Discord no servidor (listagem, detalhe e ações).
     *
     *   // Listar (filtra por tag/status)
     *   NotifyQuery::server()->discord()->tag('cadastros')->status('sent')->get();
     *
     *   // Detalhe + timeline de um envio
     *   NotifyQuery::server()->discord('uuid')->get();
     *
     *   // Cancelar (só enquanto `created`)
     *   NotifyQuery::server()->discord('uuid')->cancel();
     *
     *   // Gerenciar a mensagem já enviada
     *   NotifyQuery::server()->discord('uuid')->edit('novo texto');
     *   NotifyQuery::server()->discord('uuid')->delete();
     *
     * @param string|null $id  UUID do envio, para detalhe/ações sobre um registro específico.
     */
    public function discord(?string $id = null): ServerDiscordQuery
    {
        return new ServerDiscordQuery($this->apiUrl, $this->apiKey, $id);
    }

    // ── Teams ─────────────────────────────────────────────────────────────────

    /**
     * Recurso de Microsoft Teams no servidor (listagem, detalhe e ações).
     *
     *   // Listar (filtra por tag/status)
     *   NotifyQuery::server()->teams()->tag('relatorios')->status('sent')->get();
     *
     *   // Detalhe + timeline de um envio
     *   NotifyQuery::server()->teams('uuid')->get();
     *
     *   // Cancelar (só enquanto `created`)
     *   NotifyQuery::server()->teams('uuid')->cancel();
     *   NotifyQuery::server()->teams()->cancel('uuid');
     *
     * @param string|null $id  UUID do envio, para detalhe/ações sobre um registro específico.
     */
    public function teams(?string $id = null): ServerTeamsQuery
    {
        return new ServerTeamsQuery($this->apiUrl, $this->apiKey, $id);
    }

    // ── WebSocket ─────────────────────────────────────────────────────────────

    /**
     * Recurso de WebSocket no servidor (listagem, detalhe e ações).
     *
     *   // Listar (filtra por tag/status)
     *   NotifyQuery::server()->websocket()->tag('pedidos')->status('sent')->get();
     *
     *   // Detalhe + timeline de um evento
     *   NotifyQuery::server()->websocket('uuid')->get();
     *
     *   // Cancelar (só enquanto `created`)
     *   NotifyQuery::server()->websocket('uuid')->cancel();
     *   NotifyQuery::server()->websocket()->cancel('uuid');
     *
     * @param string|null $id  UUID do evento, para detalhe/ações sobre um registro específico.
     */
    public function websocket(?string $id = null): ServerWebSocketQuery
    {
        return new ServerWebSocketQuery($this->apiUrl, $this->apiKey, $id);
    }

    // ── Webhook ───────────────────────────────────────────────────────────────

    /**
     * Recurso de Webhook (HTTP genérico) no servidor (listagem, detalhe e ações).
     *
     *   // Listar (filtra por tag/status)
     *   NotifyQuery::server()->webhook()->tag('erp')->status('sent')->get();
     *
     *   // Detalhe + timeline de um envio
     *   NotifyQuery::server()->webhook('uuid')->get();
     *
     *   // Cancelar (só enquanto `created`)
     *   NotifyQuery::server()->webhook('uuid')->cancel();
     *   NotifyQuery::server()->webhook()->cancel('uuid');
     *
     *   // Configuração do driver generic (credencial)
     *   NotifyQuery::server()->webhook()->config()->driver('generic')->defaultUrl('...')->save();
     *
     * @param string|null $id  UUID do envio, para detalhe/ações sobre um registro específico.
     */
    public function webhook(?string $id = null): ServerWebhookQuery
    {
        return new ServerWebhookQuery($this->apiUrl, $this->apiKey, $id);
    }

    // ── Configuração de drivers ─────────────────────────────────────────────────

    /**
     * Descobre os drivers cadastráveis por canal (default_driver + credential_fields).
     * GET /api/v1/configurations/drivers
     *
     * @return array
     */
    public function drivers(): array
    {
        return $this->http()->get('/api/v1/configurations/drivers')->json('data', []);
    }

    /**
     * Lista as configurações de driver cadastradas (filtro opcional por canal).
     * GET /api/v1/configurations
     *
     * @param  string|null $channel  sms | email | push | apns | telegram | ...
     * @return array
     */
    public function configurations(?string $channel = null): array
    {
        $params = $channel ? ['channel' => $channel] : [];

        return $this->http()->get('/api/v1/configurations', $params)->json('data', []);
    }

    // Cada recurso de canal (sms, mail, push, apns, telegram, slack, discord,
    // teams, websocket) expõe ->config() para CRUD da credencial; o driver é
    // informado por ->driver('twilio') no builder.

    // ── Campanhas ─────────────────────────────────────────────────────────────

    /**
     * Recurso de campanhas no servidor (endpoints por canal).
     * Sem id → listagem; com id → detalhe (->get()) e cancelamento (->cancel()).
     *
     * @param  string      $channel  sms | mail (email é normalizado para mail)
     * @param  string|null $id       UUID da campanha retornado ao criar
     */
    public function campaigns(string $channel, ?string $id = null): ServerCampaignQuery
    {
        return new ServerCampaignQuery($this->apiUrl, $this->apiKey, $channel, $id);
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Fluent builder para listar notificações no servidor.
 *
 * NotifyQuery::server()->notifications()
 *     ->channel('sms')
 *     ->status('send')
 *     ->from('2026-01-01')
 *     ->campaignId('uuid')
 *     ->perPage(50)
 *     ->get();
 */
class ServerNotificationQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
    ) {}

    public function channel(string $channel): static
    {
        $this->params['channel'] = $channel;
        return $this;
    }

    /**
     * Status: created | sending | send | delivered | ready | error
     */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    public function from(string $date): static
    {
        $this->params['from'] = $date;
        return $this;
    }

    public function to(string $date): static
    {
        $this->params['to'] = $date;
        return $this;
    }

    public function campaignId(string $campaignId): static
    {
        $this->params['campaign_id'] = $campaignId;
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

    /**
     * Executa a consulta. Retorna array com 'data' e 'meta'.
     *
     * @return array{data: array, meta: array{total: int, per_page: int, current_page: int, last_page: int}}
     */
    public function get(): array
    {
        $response = Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->get("{$this->apiUrl}/api/v1/notifications", $this->params);

        return [
            'data' => $response->json('data', []),
            'meta' => $response->json('meta', []),
        ];
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Recurso de campanhas no servidor — listagem, detalhe e cancelamento.
 * O canal define o endpoint: /api/v1/send/campaigns/{sms|mail}.
 *
 *   // Listar
 *   NotifyQuery::server()->campaigns('sms')->status('processing')->get();
 *
 *   // Detalhe + progresso (progress_percent, pending_count)
 *   NotifyQuery::server()->campaigns('mail', 'campaign-uuid')->get();
 *
 *   // Cancelar (pending/paused/processing)
 *   NotifyQuery::server()->campaigns('sms', 'campaign-uuid')->cancel();
 *   NotifyQuery::server()->campaigns('sms')->cancel('campaign-uuid');
 *
 *   // Contatos da campanha (status/erro por destinatário) — exige o id
 *   NotifyQuery::server()->campaigns('mail', 'campaign-uuid')->contacts()->status('failed')->get();
 */
class ServerCampaignQuery
{
    protected array $params = [];

    /** Segmento do endpoint por canal: sms | mail. */
    protected string $segment;

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        string $channel,
        protected ?string $id = null,
    ) {
        $this->segment = $channel === 'sms' ? 'sms' : 'mail';
    }

    /** Filtra por tag(s) — contém qualquer uma das informadas. */
    public function tag(string|array $tag): static
    {
        $this->params['tag'] = is_array($tag) ? array_values($tag) : $tag;
        return $this;
    }

    /** status: pending | processing | paused | completed | failed | cancelled */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    public function from(string $date): static
    {
        $this->params['from'] = $date;
        return $this;
    }

    public function to(string $date): static
    {
        $this->params['to'] = $date;
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

    /**
     * Com id → detalhe + progresso de uma campanha.
     * Sem id  → listagem paginada (filtrada por status/tag/datas).
     *
     * @return array
     */
    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/send/campaigns/{$this->segment}/{$this->id}")->json() ?? [];
        }

        return $this->http()->get("/api/v1/send/campaigns/{$this->segment}", $this->params)->json() ?? [];
    }

    /**
     * Cancela a campanha — válido enquanto pending/paused/processing.
     * DELETE /api/v1/send/campaigns/{canal}/{id}
     *
     * @return array{status: bool, message: string|null, current_status: string|null, http: int}
     */
    public function cancel(?string $id = null): array
    {
        $id ??= $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => 'Campaign id is required to cancel.', 'current_status' => null, 'http' => 0];
        }

        $response = $this->http()->delete("/api/v1/send/campaigns/{$this->segment}/{$id}");

        return [
            'status'         => $response->json('status', false),
            'message'        => $response->json('message'),
            'current_status' => $response->json('current_status'),
            'http'           => $response->status(),
        ];
    }

    /**
     * Sub-recurso de contatos da campanha — status/erro por destinatário.
     * Exige o id da campanha (->campaigns($canal, $id)->contacts()).
     */
    public function contacts(): ServerCampaignContactQuery
    {
        return new ServerCampaignContactQuery($this->apiUrl, $this->apiKey, $this->segment, $this->id);
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Sub-recurso de contatos de uma campanha — status e motivo de falha por destinatário.
 * Instanciado via NotifyQuery::server()->campaigns($canal, $id)->contacts().
 *
 *   NotifyQuery::server()->campaigns('mail', 'campaign-uuid')->contacts()
 *       ->status('failed')   // pending | sending | sent | failed | skipped
 *       ->page(2)
 *       ->get();
 *
 * GET /api/v1/send/campaigns/{sms|mail}/{id}/contacts?status=&page=
 */
class ServerCampaignContactQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected string $segment,
        protected ?string $campaignId = null,
    ) {}

    /** status: pending | sending | sent | failed | skipped */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    public function page(int $page): static
    {
        $this->params['page'] = $page;
        return $this;
    }

    /**
     * Lista os contatos (paginado). Retorna a resposta crua do servidor:
     * ['status' => bool, 'data' => [...], 'pagination' => ['current_page','per_page','total','last_page']].
     *
     * @return array
     */
    public function get(): array
    {
        if ($this->campaignId === null) {
            return ['status' => false, 'data' => [], 'pagination' => []];
        }

        return $this->http()
            ->get("/api/v1/send/campaigns/{$this->segment}/{$this->campaignId}/contacts", $this->params)
            ->json() ?? [];
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Recurso de SMS no servidor — listagem, detalhe e ações.
 * Instanciado via NotifyQuery::server()->sms() (lista) ou ->sms($id) (registro).
 *
 *   // Listar
 *   NotifyQuery::server()->sms()->tag('promo')->status('delivered')->get();
 *
 *   // Detalhe + timeline
 *   NotifyQuery::server()->sms('uuid')->get();
 *
 *   // Cancelar (só enquanto `created`)
 *   NotifyQuery::server()->sms('uuid')->cancel();
 *   NotifyQuery::server()->sms()->cancel('uuid');
 */
class ServerSmsQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected ?string $id = null,
    ) {}

    /**
     * Filtra por tag(s) — contém qualquer uma das informadas.
     * Sem chamar tag() o servidor retorna só os SMS SEM tag.
     */
    public function tag(string|array $tag): static
    {
        $this->params['tag'] = is_array($tag) ? array_values($tag) : $tag;
        return $this;
    }

    /** status: created | sending | send | delivered | error | cancelled */
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

    /**
     * Com id → detalhe + timeline de eventos de um SMS.
     * Sem id  → listagem paginada (filtrada por tag/status).
     *
     * @return array
     */
    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/sms/{$this->id}")->json() ?? [];
        }

        return $this->http()->get('/api/v1/sms', $this->params)->json() ?? [];
    }

    /**
     * Cancela um SMS — só funciona enquanto o status for `created` (na fila).
     * Usa o id do recurso (->sms($id)->cancel()) ou um id explícito (->sms()->cancel($id)).
     *
     * @return array{status: bool, message: string|null, current_status: string|null, http: int}
     */
    public function cancel(?string $id = null): array
    {
        $id ??= $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => 'SMS id is required to cancel.', 'current_status' => null, 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/sms/{$id}/cancel");

        return [
            'status'         => $response->json('status', false),
            'message'        => $response->json('message'),
            'current_status' => $response->json('current_status'),
            'http'           => $response->status(),
        ];
    }

    /**
     * Configuração de credencial do canal SMS (informe o driver com ->driver()).
     * Sem id → criar; com id → atualizar/remover/definir-padrão/detalhe.
     */
    public function config(?string $id = null): ServerDriverConfig
    {
        return new ServerDriverConfig($this->apiUrl, $this->apiKey, 'sms', $id);
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Recurso de e-mail no servidor — listagem, detalhe e ações.
 * Instanciado via NotifyQuery::server()->mail() (lista) ou ->mail($id) (registro).
 *
 *   // Listar
 *   NotifyQuery::server()->mail()->tag('pedido')->status('delivered')->get();
 *
 *   // Detalhe + timeline
 *   NotifyQuery::server()->mail('uuid')->get();
 *
 *   // Cancelar (só enquanto `created`)
 *   NotifyQuery::server()->mail('uuid')->cancel();
 *   NotifyQuery::server()->mail()->cancel('uuid');
 */
class ServerMailQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected ?string $id = null,
    ) {}

    /**
     * Filtra por tag(s) — contém qualquer uma das informadas.
     * Sem chamar tag() o servidor retorna só os e-mails SEM tag.
     */
    public function tag(string|array $tag): static
    {
        $this->params['tag'] = is_array($tag) ? array_values($tag) : $tag;
        return $this;
    }

    /** status: created | sending | sent | delivered | ready | error | cancelled */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    /** Itens por página (1–100, default 20 no servidor). */
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

    /**
     * Com id → detalhe + timeline de eventos de um e-mail.
     * Sem id  → listagem paginada (filtrada por tag/status).
     *
     * @return array
     */
    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/email/{$this->id}")->json() ?? [];
        }

        return $this->http()->get('/api/v1/email', $this->params)->json() ?? [];
    }

    /**
     * Cancela um e-mail — só funciona enquanto o status for `created` (na fila).
     * Usa o id do recurso (->mail($id)->cancel()) ou um id explícito (->mail()->cancel($id)).
     *
     * @return array{status: bool, message: string|null, notification_id: string|null, current_status: string|null, http: int}
     */
    public function cancel(?string $id = null): array
    {
        $id ??= $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => 'Email id is required to cancel.', 'notification_id' => null, 'current_status' => null, 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/email/{$id}/cancel");

        return [
            'status'          => $response->json('status', false),
            'message'         => $response->json('message'),
            'notification_id' => $response->json('notification_id'),
            'current_status'  => $response->json('current_status'),
            'http'            => $response->status(),
        ];
    }

    /**
     * Configuração de credencial do canal Email (informe o driver com ->driver()).
     * Sem id → criar; com id → atualizar/remover/definir-padrão/detalhe.
     */
    public function config(?string $id = null): ServerDriverConfig
    {
        return new ServerDriverConfig($this->apiUrl, $this->apiKey, 'email', $id);
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Recurso de push (FCM) no servidor — listagem, detalhe e ações.
 * Instanciado via NotifyQuery::server()->push() (lista) ou ->push($id) (registro).
 *
 *   // Listar
 *   NotifyQuery::server()->push()->tag('promo')->status('sent')->get();
 *
 *   // Detalhe + timeline
 *   NotifyQuery::server()->push('uuid')->get();
 *
 *   // Cancelar (só enquanto `created`)
 *   NotifyQuery::server()->push('uuid')->cancel();
 *   NotifyQuery::server()->push()->cancel('uuid');
 */
class ServerPushQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected ?string $id = null,
    ) {}

    /**
     * Filtra por tag. Sem chamar tag() o servidor retorna só os push SEM tag.
     * (No push a tag é uma string única.)
     */
    public function tag(string $tag): static
    {
        $this->params['tag'] = $tag;
        return $this;
    }

    /** status: created | sending | sent | error | failed | cancelled */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    /** Itens por página (1–100, default 20 no servidor). */
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

    /**
     * Com id → detalhe + timeline de eventos de um push.
     * Sem id  → listagem paginada (filtrada por tag/status).
     *
     * @return array
     */
    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/push/{$this->id}")->json() ?? [];
        }

        return $this->http()->get('/api/v1/push', $this->params)->json() ?? [];
    }

    /**
     * Cancela um push — só funciona enquanto o status for `created` (na fila).
     * Usa o id do recurso (->push($id)->cancel()) ou um id explícito (->push()->cancel($id)).
     *
     * @return array{status: bool, message: string|null, current_status: string|null, http: int}
     */
    public function cancel(?string $id = null): array
    {
        $id ??= $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => 'Push id is required to cancel.', 'current_status' => null, 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/push/{$id}/cancel");

        return [
            'status'         => $response->json('status', false),
            'message'        => $response->json('message'),
            'current_status' => $response->json('current_status'),
            'http'           => $response->status(),
        ];
    }

    /**
     * Configuração de credencial do canal Push (informe o driver com ->driver()).
     * Sem id → criar; com id → atualizar/remover/definir-padrão/detalhe.
     */
    public function config(?string $id = null): ServerDriverConfig
    {
        return new ServerDriverConfig($this->apiUrl, $this->apiKey, 'push', $id);
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Recurso de push APNs (Apple) no servidor — listagem, detalhe e ações.
 * Instanciado via NotifyQuery::server()->apns() (lista) ou ->apns($id) (registro).
 *
 *   NotifyQuery::server()->apns()->tag('entregas')->status('sent')->get();
 *
 *   // Detalhe + timeline
 *   NotifyQuery::server()->apns('uuid')->get();
 *
 *   // Cancelar (só enquanto `created`)
 *   NotifyQuery::server()->apns('uuid')->cancel();
 *   NotifyQuery::server()->apns()->cancel('uuid');
 */
class ServerApnsQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected ?string $id = null,
    ) {}

    /** Filtra por tag. Sem chamar tag() o servidor retorna só os envios SEM tag. */
    public function tag(string|array $tag): static
    {
        $this->params['tag'] = is_array($tag) ? implode(',', $tag) : $tag;
        return $this;
    }

    /** status: created | sending | sent | error | failed | cancelled */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    /** Itens por página (1–100, default 20 no servidor). */
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

    /**
     * Com id → detalhe + timeline de eventos de um envio.
     * Sem id  → listagem paginada (filtrada por tag/status).
     *
     * @return array
     */
    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/apns/{$this->id}")->json() ?? [];
        }

        return $this->http()->get('/api/v1/apns', $this->params)->json() ?? [];
    }

    /**
     * Cancela um envio APNs — só funciona enquanto o status for `created` (na fila).
     * Usa o id do recurso (->apns($id)->cancel()) ou um id explícito (->apns()->cancel($id)).
     *
     * @return array{status: bool, message: string|null, current_status: string|null, http: int}
     */
    public function cancel(?string $id = null): array
    {
        $id ??= $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => 'APNs id is required to cancel.', 'current_status' => null, 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/apns/{$id}/cancel");

        return [
            'status'         => $response->json('status', false),
            'message'        => $response->json('message'),
            'current_status' => $response->json('current_status'),
            'http'           => $response->status(),
        ];
    }

    /**
     * Configuração da credencial APNS (CRUD de driver config).
     * Sem id → criar; com id → atualizar/remover/definir-padrão/detalhe.
     */
    public function config(?string $id = null): ServerDriverConfig
    {
        return new ServerDriverConfig($this->apiUrl, $this->apiKey, 'apns', $id);
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Recurso de Telegram no servidor — listagem, detalhe e ações.
 * Instanciado via NotifyQuery::server()->telegram() (lista) ou ->telegram($id) (registro).
 *
 *   NotifyQuery::server()->telegram()->tag('pedidos')->status('sent')->get();
 *
 *   // Detalhe + timeline
 *   NotifyQuery::server()->telegram('uuid')->get();
 *
 *   // Cancelar (só enquanto `created`)
 *   NotifyQuery::server()->telegram('uuid')->cancel();
 *   NotifyQuery::server()->telegram()->cancel('uuid');
 *
 *   // Gerenciar a mensagem já enviada (síncrono, sobre o registro)
 *   NotifyQuery::server()->telegram('uuid')->edit('novo texto');
 *   NotifyQuery::server()->telegram('uuid')->editCaption('nova legenda');
 *   NotifyQuery::server()->telegram('uuid')->delete();
 *   NotifyQuery::server()->telegram('uuid')->pin(disableNotification: true);
 */
class ServerTelegramQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected ?string $id = null,
    ) {}

    /** Filtra por tag. Sem chamar tag() o servidor retorna só os envios SEM tag. */
    public function tag(string|array $tag): static
    {
        $this->params['tag'] = is_array($tag) ? implode(',', $tag) : $tag;
        return $this;
    }

    /** status: created | sending | sent | error | failed | cancelled */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    /** Itens por página (1–100, default 20 no servidor). */
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

    /**
     * Com id → detalhe + timeline de eventos de um envio.
     * Sem id  → listagem paginada (filtrada por tag/status).
     *
     * @return array
     */
    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/telegram/{$this->id}")->json() ?? [];
        }

        return $this->http()->get('/api/v1/telegram', $this->params)->json() ?? [];
    }

    /**
     * Cancela um envio Telegram — só funciona enquanto o status for `created` (na fila).
     * Usa o id do recurso (->telegram($id)->cancel()) ou um id explícito (->telegram()->cancel($id)).
     *
     * @return array{status: bool, message: string|null, current_status: string|null, http: int}
     */
    public function cancel(?string $id = null): array
    {
        $id ??= $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => 'Telegram id is required to cancel.', 'current_status' => null, 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/telegram/{$id}/cancel");

        return [
            'status'         => $response->json('status', false),
            'message'        => $response->json('message'),
            'current_status' => $response->json('current_status'),
            'http'           => $response->status(),
        ];
    }

    // ── Gerência de mensagem (síncrono, sobre o registro) ───────────────────────

    /**
     * Edita o texto de uma mensagem já enviada. Requer o id do recurso:
     * NotifyQuery::server()->telegram($id)->edit('novo texto').
     *
     * @param string      $message    Novo texto
     * @param string|null $parseMode  Markdown | MarkdownV2 | HTML (opcional)
     */
    public function edit(string $message, ?string $parseMode = null): array
    {
        return $this->messageAction('edit', array_filter([
            'message'    => $message,
            'parse_mode' => $parseMode,
        ], fn ($v) => !is_null($v)));
    }

    /**
     * Edita a legenda de uma mensagem de mídia (foto/documento/vídeo).
     *
     * @param string      $caption    Nova legenda
     * @param string|null $parseMode  Markdown | MarkdownV2 | HTML (opcional)
     */
    public function editCaption(string $caption, ?string $parseMode = null): array
    {
        return $this->messageAction('edit-caption', array_filter([
            'message'    => $caption,
            'parse_mode' => $parseMode,
        ], fn ($v) => !is_null($v)));
    }

    /** Apaga uma mensagem já enviada. */
    public function delete(): array
    {
        return $this->messageAction('delete', []);
    }

    /**
     * Fixa uma mensagem já enviada no chat.
     *
     * @param bool $disableNotification  true = fixa sem notificar os membros
     */
    public function pin(bool $disableNotification = false): array
    {
        return $this->messageAction('pin', ['disable_notification' => $disableNotification]);
    }

    /**
     * Interno: chama /api/v1/telegram/messages/{action} com o notification_id do
     * recurso (o servidor resolve chat_id + message_id salvos).
     *
     * @return array{status: bool, message: string|null, http: int}
     */
    protected function messageAction(string $action, array $payload): array
    {
        if ($this->id === null) {
            return ['status' => false, 'message' => 'Telegram notification id is required.', 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/telegram/messages/{$action}", array_merge(
            ['notification_id' => $this->id],
            $payload,
        ));

        return [
            'status'  => $response->json('status', false),
            'message' => $response->json('message'),
            'http'    => $response->status(),
        ];
    }

    /**
     * Configuração da credencial Telegram (CRUD de driver config).
     * Sem id → criar; com id → atualizar/remover/definir-padrão/detalhe.
     */
    public function config(?string $id = null): ServerDriverConfig
    {
        return new ServerDriverConfig($this->apiUrl, $this->apiKey, 'telegram', $id);
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Recurso de Slack no servidor — listagem, detalhe e ações.
 * Instanciado via NotifyQuery::server()->slack() (lista) ou ->slack($id) (registro).
 *
 *   NotifyQuery::server()->slack()->tag('deploys')->status('sent')->get();
 *
 *   // Detalhe + timeline
 *   NotifyQuery::server()->slack('uuid')->get();
 *
 *   // Cancelar (só enquanto `created`)
 *   NotifyQuery::server()->slack('uuid')->cancel();
 *   NotifyQuery::server()->slack()->cancel('uuid');
 *
 *   // Gerenciar a mensagem já enviada (síncrono, só Bot Token)
 *   NotifyQuery::server()->slack('uuid')->edit('novo texto');
 *   NotifyQuery::server()->slack('uuid')->delete();
 */
class ServerSlackQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected ?string $id = null,
    ) {}

    /** Filtra por tag. Sem chamar tag() o servidor retorna só os envios SEM tag. */
    public function tag(string|array $tag): static
    {
        $this->params['tag'] = is_array($tag) ? implode(',', $tag) : $tag;
        return $this;
    }

    /** status: created | sending | sent | error | failed | cancelled */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    /** Itens por página (1–100, default 20 no servidor). */
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

    /**
     * Com id → detalhe + timeline de eventos de um envio.
     * Sem id  → listagem paginada (filtrada por tag/status).
     *
     * @return array
     */
    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/slack/{$this->id}")->json() ?? [];
        }

        return $this->http()->get('/api/v1/slack', $this->params)->json() ?? [];
    }

    /**
     * Cancela um envio Slack — só funciona enquanto o status for `created` (na fila).
     * Usa o id do recurso (->slack($id)->cancel()) ou um id explícito (->slack()->cancel($id)).
     *
     * @return array{status: bool, message: string|null, current_status: string|null, http: int}
     */
    public function cancel(?string $id = null): array
    {
        $id ??= $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => 'Slack id is required to cancel.', 'current_status' => null, 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/slack/{$id}/cancel");

        return [
            'status'         => $response->json('status', false),
            'message'        => $response->json('message'),
            'current_status' => $response->json('current_status'),
            'http'           => $response->status(),
        ];
    }

    // ── Gerência de mensagem (síncrono, só Bot Token) ───────────────────────────

    /**
     * Edita (chat.update) uma mensagem já enviada. Só funciona no modo Bot Token.
     * Requer o id do recurso: NotifyQuery::server()->slack($id)->edit('novo texto').
     */
    public function edit(string $message): array
    {
        return $this->messageAction('edit', ['message' => $message]);
    }

    /**
     * Apaga (chat.delete) uma mensagem já enviada. Só funciona no modo Bot Token.
     * Requer o id do recurso: NotifyQuery::server()->slack($id)->delete().
     */
    public function delete(): array
    {
        return $this->messageAction('delete', []);
    }

    /**
     * Interno: chama /api/v1/slack/messages/{action} com o notification_id do recurso.
     *
     * @return array{status: bool, message: string|null, http: int}
     */
    protected function messageAction(string $action, array $payload): array
    {
        if ($this->id === null) {
            return ['status' => false, 'message' => 'Slack notification id is required.', 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/slack/messages/{$action}", array_merge(
            ['notification_id' => $this->id],
            $payload,
        ));

        return [
            'status'  => $response->json('status', false),
            'message' => $response->json('message'),
            'http'    => $response->status(),
        ];
    }

    /**
     * Configuração da credencial Slack (CRUD de driver config).
     * Sem id → criar; com id → atualizar/remover/definir-padrão/detalhe.
     */
    public function config(?string $id = null): ServerDriverConfig
    {
        return new ServerDriverConfig($this->apiUrl, $this->apiKey, 'slack', $id);
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Recurso de Discord no servidor — listagem, detalhe e ações.
 * Instanciado via NotifyQuery::server()->discord() (lista) ou ->discord($id) (registro).
 *
 *   NotifyQuery::server()->discord()->tag('cadastros')->status('sent')->get();
 *
 *   // Detalhe + timeline
 *   NotifyQuery::server()->discord('uuid')->get();
 *
 *   // Cancelar (só enquanto `created`)
 *   NotifyQuery::server()->discord('uuid')->cancel();
 *   NotifyQuery::server()->discord()->cancel('uuid');
 *
 *   // Gerenciar a mensagem já enviada (síncrono, sobre o registro)
 *   NotifyQuery::server()->discord('uuid')->edit('novo texto');
 *   NotifyQuery::server()->discord('uuid')->delete();
 */
class ServerDiscordQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected ?string $id = null,
    ) {}

    /** Filtra por tag. Sem chamar tag() o servidor retorna só os envios SEM tag. */
    public function tag(string|array $tag): static
    {
        $this->params['tag'] = is_array($tag) ? implode(',', $tag) : $tag;
        return $this;
    }

    /** status: created | sending | sent | error | failed | cancelled */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    /** Itens por página (1–100, default 20 no servidor). */
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

    /**
     * Com id → detalhe + timeline de eventos de um envio.
     * Sem id  → listagem paginada (filtrada por tag/status).
     *
     * @return array
     */
    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/discord/{$this->id}")->json() ?? [];
        }

        return $this->http()->get('/api/v1/discord', $this->params)->json() ?? [];
    }

    /**
     * Cancela um envio Discord — só funciona enquanto o status for `created` (na fila).
     * Usa o id do recurso (->discord($id)->cancel()) ou um id explícito (->discord()->cancel($id)).
     *
     * @return array{status: bool, message: string|null, current_status: string|null, http: int}
     */
    public function cancel(?string $id = null): array
    {
        $id ??= $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => 'Discord id is required to cancel.', 'current_status' => null, 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/discord/{$id}/cancel");

        return [
            'status'         => $response->json('status', false),
            'message'        => $response->json('message'),
            'current_status' => $response->json('current_status'),
            'http'           => $response->status(),
        ];
    }

    // ── Gerência de mensagem (síncrono, sobre o registro) ───────────────────────

    /**
     * Edita (PATCH) uma mensagem já enviada.
     * Requer o id do recurso: NotifyQuery::server()->discord($id)->edit('novo texto').
     */
    public function edit(string $message): array
    {
        return $this->messageAction('edit', ['message' => $message]);
    }

    /**
     * Apaga (DELETE) uma mensagem já enviada.
     * Requer o id do recurso: NotifyQuery::server()->discord($id)->delete().
     */
    public function delete(): array
    {
        return $this->messageAction('delete', []);
    }

    /**
     * Interno: chama /api/v1/send/discord/{action} com o notification_id do recurso.
     *
     * @return array{status: bool, message: string|null, http: int}
     */
    protected function messageAction(string $action, array $payload): array
    {
        if ($this->id === null) {
            return ['status' => false, 'message' => 'Discord notification id is required.', 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/send/discord/{$action}", array_merge(
            ['notification_id' => $this->id],
            $payload,
        ));

        return [
            'status'  => $response->json('status', false),
            'message' => $response->json('message'),
            'http'    => $response->status(),
        ];
    }

    /**
     * Configuração da credencial Discord (CRUD de driver config).
     * Sem id → criar; com id → atualizar/remover/definir-padrão/detalhe.
     */
    public function config(?string $id = null): ServerDriverConfig
    {
        return new ServerDriverConfig($this->apiUrl, $this->apiKey, 'discord', $id);
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Recurso de Microsoft Teams no servidor — listagem, detalhe e ações.
 * Instanciado via NotifyQuery::server()->teams() (lista) ou ->teams($id) (registro).
 *
 *   NotifyQuery::server()->teams()->tag('relatorios')->status('sent')->get();
 *
 *   // Detalhe + timeline
 *   NotifyQuery::server()->teams('uuid')->get();
 *
 *   // Cancelar (só enquanto `created`)
 *   NotifyQuery::server()->teams('uuid')->cancel();
 *   NotifyQuery::server()->teams()->cancel('uuid');
 */
class ServerTeamsQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected ?string $id = null,
    ) {}

    /** Filtra por tag. Sem chamar tag() o servidor retorna só os envios SEM tag. */
    public function tag(string|array $tag): static
    {
        $this->params['tag'] = is_array($tag) ? implode(',', $tag) : $tag;
        return $this;
    }

    /** status: created | sending | sent | error | failed | cancelled */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    /** Itens por página (1–100, default 20 no servidor). */
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

    /**
     * Com id → detalhe + timeline de eventos de um envio.
     * Sem id  → listagem paginada (filtrada por tag/status).
     *
     * @return array
     */
    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/teams/{$this->id}")->json() ?? [];
        }

        return $this->http()->get('/api/v1/teams', $this->params)->json() ?? [];
    }

    /**
     * Cancela um envio Teams — só funciona enquanto o status for `created` (na fila).
     * Usa o id do recurso (->teams($id)->cancel()) ou um id explícito (->teams()->cancel($id)).
     *
     * @return array{status: bool, message: string|null, current_status: string|null, http: int}
     */
    public function cancel(?string $id = null): array
    {
        $id ??= $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => 'Teams id is required to cancel.', 'current_status' => null, 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/teams/{$id}/cancel");

        return [
            'status'         => $response->json('status', false),
            'message'        => $response->json('message'),
            'current_status' => $response->json('current_status'),
            'http'           => $response->status(),
        ];
    }

    /**
     * Configuração da credencial Teams (CRUD de driver config).
     * Sem id → criar; com id → atualizar/remover/definir-padrão/detalhe.
     */
    public function config(?string $id = null): ServerDriverConfig
    {
        return new ServerDriverConfig($this->apiUrl, $this->apiKey, 'teams', $id);
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Recurso de WebSocket no servidor — listagem, detalhe e ações.
 * Instanciado via NotifyQuery::server()->websocket() (lista) ou ->websocket($id) (registro).
 *
 *   NotifyQuery::server()->websocket()->tag('pedidos')->status('sent')->get();
 *
 *   // Detalhe + timeline
 *   NotifyQuery::server()->websocket('uuid')->get();
 *
 *   // Cancelar (só enquanto `created`)
 *   NotifyQuery::server()->websocket('uuid')->cancel();
 *   NotifyQuery::server()->websocket()->cancel('uuid');
 */
class ServerWebSocketQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected ?string $id = null,
    ) {}

    /** Filtra por tag. Sem chamar tag() o servidor retorna só os eventos SEM tag. */
    public function tag(string|array $tag): static
    {
        $this->params['tag'] = is_array($tag) ? implode(',', $tag) : $tag;
        return $this;
    }

    /** status: created | sending | sent | error | failed | cancelled */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    /** Itens por página (1–100, default 20 no servidor). */
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

    /**
     * Com id → detalhe + timeline de eventos de um envio.
     * Sem id  → listagem paginada (filtrada por tag/status).
     *
     * @return array
     */
    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/websocket/{$this->id}")->json() ?? [];
        }

        return $this->http()->get('/api/v1/websocket', $this->params)->json() ?? [];
    }

    /**
     * Cancela um evento WebSocket — só funciona enquanto o status for `created` (na fila).
     * Usa o id do recurso (->websocket($id)->cancel()) ou um id explícito (->websocket()->cancel($id)).
     *
     * @return array{status: bool, message: string|null, current_status: string|null, http: int}
     */
    public function cancel(?string $id = null): array
    {
        $id ??= $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => 'WebSocket id is required to cancel.', 'current_status' => null, 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/websocket/{$id}/cancel");

        return [
            'status'         => $response->json('status', false),
            'message'        => $response->json('message'),
            'current_status' => $response->json('current_status'),
            'http'           => $response->status(),
        ];
    }

    /**
     * Configuração de credencial do canal WebSocket (informe o driver com ->driver()).
     * Sem id → criar; com id → atualizar/remover/definir-padrão/detalhe.
     */
    public function config(?string $id = null): ServerDriverConfig
    {
        return new ServerDriverConfig($this->apiUrl, $this->apiKey, 'websocket', $id);
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Recurso de Webhook (HTTP genérico) no servidor — listagem, detalhe e ações.
 * Instanciado via NotifyQuery::server()->webhook() (lista) ou ->webhook($id) (registro).
 *
 *   NotifyQuery::server()->webhook()->tag('erp')->status('sent')->get();
 *
 *   // Detalhe + timeline
 *   NotifyQuery::server()->webhook('uuid')->get();
 *
 *   // Cancelar (só enquanto `created`)
 *   NotifyQuery::server()->webhook('uuid')->cancel();
 *   NotifyQuery::server()->webhook()->cancel('uuid');
 */
class ServerWebhookQuery
{
    protected array $params = [];

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected ?string $id = null,
    ) {}

    /** Filtra por tag. Sem chamar tag() o servidor retorna só os envios SEM tag. */
    public function tag(string|array $tag): static
    {
        $this->params['tag'] = is_array($tag) ? implode(',', $tag) : $tag;
        return $this;
    }

    /** status: created | sending | sent | error | cancelled */
    public function status(string $status): static
    {
        $this->params['status'] = $status;
        return $this;
    }

    /** Itens por página (1–100, default 20 no servidor). */
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

    /**
     * Com id → detalhe + timeline de eventos de um envio.
     * Sem id  → listagem paginada (filtrada por tag/status).
     *
     * @return array
     */
    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/webhook/{$this->id}")->json() ?? [];
        }

        return $this->http()->get('/api/v1/webhook', $this->params)->json() ?? [];
    }

    /**
     * Cancela um envio webhook — só funciona enquanto o status for `created` (na fila).
     * Usa o id do recurso (->webhook($id)->cancel()) ou um id explícito (->webhook()->cancel($id)).
     *
     * @return array{status: bool, message: string|null, notification_id: string|null, current_status: string|null, http: int}
     */
    public function cancel(?string $id = null): array
    {
        $id ??= $this->id;

        if ($id === null) {
            return ['status' => false, 'message' => 'Webhook id is required to cancel.', 'notification_id' => null, 'current_status' => null, 'http' => 0];
        }

        $response = $this->http()->post("/api/v1/webhook/{$id}/cancel");

        return [
            'status'          => $response->json('status', false),
            'message'         => $response->json('message'),
            'notification_id' => $response->json('notification_id'),
            'current_status'  => $response->json('current_status'),
            'http'            => $response->status(),
        ];
    }

    /**
     * Configuração de credencial do canal Webhook (driver `generic`).
     * Sem id → criar; com id → atualizar/remover/definir-padrão/detalhe.
     */
    public function config(?string $id = null): ServerDriverConfig
    {
        return new ServerDriverConfig($this->apiUrl, $this->apiKey, 'webhook', $id);
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Builder de configuração de credencial de driver no servidor (CRUD).
 * Recurso: /api/v1/configurations. Obtido por NotifyQuery::server()->{canal}()->config().
 * O driver é informado por ->driver('twilio').
 *
 *   // Criar (sem id → POST)
 *   NotifyQuery::server()->push()->config()
 *       ->driver('fcm')
 *       ->label('FCM Android')
 *       ->projectId('meu-projeto')
 *       ->credentialsFile('/path/sa.json')
 *       ->asDefault()
 *       ->save();
 *
 *   // Atualizar (com id → PUT, merge parcial) — mesmo save()
 *   NotifyQuery::server()->push()->config('uuid')->apiKey('nova')->save();
 *
 *   // Detalhe / padrão / remover
 *   NotifyQuery::server()->push()->config('uuid')->get();
 *   NotifyQuery::server()->push()->config('uuid')->setDefault();
 *   NotifyQuery::server()->push()->config('uuid')->delete();
 */
class ServerDriverConfig
{
    protected ?string $driver = null;
    protected ?string $label = null;
    protected array $credentials = [];
    protected ?bool $isDefault = null;
    protected ?bool $active = null;

    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected string $channel,
        protected ?string $id = null,
    ) {}

    // ── Opções gerais ───────────────────────────────────────────────────────────

    /** Driver desta credencial (ex.: twilio, smtp, fcm, pusher). Obrigatório ao criar. */
    public function driver(string $driver): static
    {
        $this->driver = $driver;
        return $this;
    }

    public function label(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    /** Define esta configuração como padrão do canal ao salvar/atualizar. */
    public function asDefault(bool $isDefault = true): static
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    public function active(bool $active = true): static
    {
        $this->active = $active;
        return $this;
    }

    /** Define uma credencial avulsa (chave => valor). */
    public function credential(string $key, mixed $value): static
    {
        $this->credentials[$key] = $value;
        return $this;
    }

    /** Mescla um array de credenciais (chaves do servidor). */
    public function credentials(array $credentials): static
    {
        $this->credentials = array_merge($this->credentials, $credentials);
        return $this;
    }

    // ── Credenciais — SMS (Twilio: sid/token/from · ClickSend: username/api_key/from · Mobizon: key/api_server)
    public function sid(string $sid): static            { return $this->credential('sid', $sid); }
    public function token(string $token): static        { return $this->credential('token', $token); }
    public function from(string $from): static          { return $this->credential('from', $from); }
    public function key(string $key): static            { return $this->credential('key', $key); }
    public function apiServer(string $apiServer): static { return $this->credential('api_server', $apiServer); }

    // ── Credenciais — Email (SMTP / Mailgun / Resend / SendGrid / SES / Postmark)
    public function host(string $host): static               { return $this->credential('host', $host); }
    public function port(int $port): static                  { return $this->credential('port', $port); }
    public function username(string $username): static       { return $this->credential('username', $username); }
    public function password(string $password): static       { return $this->credential('password', $password); }
    public function encryption(string $encryption): static   { return $this->credential('encryption', $encryption); }
    public function domain(string $domain): static           { return $this->credential('domain', $domain); }
    public function secret(string $secret): static           { return $this->credential('secret', $secret); }
    public function endpoint(string $endpoint): static       { return $this->credential('endpoint', $endpoint); }
    public function apiKey(string $apiKey): static           { return $this->credential('api_key', $apiKey); }
    public function region(string $region): static           { return $this->credential('region', $region); }

    // ── Credenciais — FCM / APNS ────────────────────────────────────────────────
    public function projectId(string $projectId): static     { return $this->credential('project_id', $projectId); }

    /**
     * Lê o arquivo JSON do Service Account (FCM) e envia o CONTEÚDO parseado em
     * `credentials_json` — não o caminho. Ex.: ->credentialsFile(storage_path('app/fcm.json')).
     */
    public function credentialsFile(string $path): static
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new \InvalidArgumentException("Service Account file not found or unreadable: {$path}");
        }

        $json = json_decode($contents, true);

        if (!is_array($json)) {
            throw new \InvalidArgumentException("Service Account file is not valid JSON: {$path}");
        }

        return $this->credential('credentials_json', $json);
    }

    /**
     * Service Account inline em `credentials_json`. Aceita array ou string JSON
     * (a string é decodificada para objeto). Alternativa ao ->credentialsFile().
     */
    public function credentialsJson(array|string $json): static
    {
        if (is_string($json)) {
            $decoded = json_decode($json, true);
            $json = is_array($decoded) ? $decoded : $json;
        }

        return $this->credential('credentials_json', $json);
    }

    public function keyPath(string $path): static             { return $this->credential('key_path', $path); }
    public function keyId(string $keyId): static              { return $this->credential('key_id', $keyId); }
    public function teamId(string $teamId): static            { return $this->credential('team_id', $teamId); }
    public function bundleId(string $bundleId): static        { return $this->credential('bundle_id', $bundleId); }
    public function production(bool $production = true): static { return $this->credential('production', $production); }

    // ── Credenciais — Telegram / Slack / Discord / Teams ────────────────────────
    public function botToken(string $botToken): static       { return $this->credential('bot_token', $botToken); }
    public function webhookUrl(string $url): static          { return $this->credential('webhook_url', $url); }
    public function defaultChannel(string $channel): static  { return $this->credential('default_channel', $channel); }
    public function avatarUrl(string $url): static           { return $this->credential('avatar_url', $url); }
    public function defaultUsername(string $username): static { return $this->credential('username', $username); }
    public function themeColor(string $color): static        { return $this->credential('theme_color', $color); }

    // ── Credenciais — Pusher (WebSocket) ────────────────────────────────────────
    public function appId(string $appId): static             { return $this->credential('app_id', $appId); }
    public function cluster(string $cluster): static         { return $this->credential('cluster', $cluster); }
    public function pusherHost(string $host): static         { return $this->credential('host', $host); }
    public function pusherPort(int $port): static            { return $this->credential('port', $port); }
    public function pusherScheme(string $scheme): static     { return $this->credential('scheme', $scheme); }

    // ── Credenciais — Webhook (driver generic) ──────────────────────────────────
    public function defaultUrl(string $url): static          { return $this->credential('default_url', $url); }
    public function timeout(int $seconds): static            { return $this->credential('timeout', $seconds); }
    public function authType(string $type): static           { return $this->credential('auth_type', $type); }
    public function authToken(string $token): static         { return $this->credential('auth_token', $token); }
    public function authUser(string $user): static           { return $this->credential('auth_user', $user); }
    public function authPassword(string $password): static   { return $this->credential('auth_password', $password); }
    public function apiKeyHeader(string $header): static      { return $this->credential('api_key_header', $header); }
    public function injectMetadata(bool $inject = true): static { return $this->credential('inject_metadata', $inject); }

    // ── Ações ───────────────────────────────────────────────────────────────────

    /**
     * Salva a configuração no servidor — decide sozinho entre criar e atualizar:
     *   - sem id (config())      → POST  /api/v1/configurations         (cadastro)
     *   - com id (config('uuid'))→ PUT   /api/v1/configurations/{id}    (atualização, merge parcial)
     *
     * No cadastro o driver é obrigatório; na atualização o driver não muda
     * (envia só label/credentials/is_default/active).
     *
     * @return array
     */
    public function save(): array
    {
        // Atualização (PUT) quando há id do recurso.
        if ($this->id !== null) {
            return $this->http()->put("/api/v1/configurations/{$this->id}", array_filter([
                'label'       => $this->label,
                'credentials' => $this->credentials ?: null,
                'is_default'  => $this->isDefault,
                'active'      => $this->active,
            ], fn ($v) => !is_null($v)))->json() ?? [];
        }

        // Cadastro (POST).
        if ($this->driver === null) {
            return ['status' => false, 'message' => 'Driver is required (use ->driver(...)).', 'http' => 0];
        }

        return $this->http()->post('/api/v1/configurations', array_filter([
            'channel'     => $this->channel,
            'driver'      => $this->driver,
            'label'       => $this->label,
            'credentials' => $this->credentials ?: null,
            'is_default'  => $this->isDefault,
            'active'      => $this->active,
        ], fn ($v) => !is_null($v)))->json() ?? [];
    }

    /**
     * Busca configuração(ões) no servidor:
     *   - com id (config('uuid')) → detalhe (metadados + credential_keys, nunca valores).
     *     GET /api/v1/configurations/{id}.
     *   - sem id (config())       → lista as configs ativas deste canal.
     *     GET /api/v1/configurations?channel={channel}.
     *
     * @return array
     */
    public function get(): array
    {
        if ($this->id !== null) {
            return $this->http()->get("/api/v1/configurations/{$this->id}")->json('data', []);
        }

        return $this->http()->get('/api/v1/configurations', ['channel' => $this->channel])->json('data', []);
    }

    /** Define como padrão do canal. PATCH /api/v1/configurations/{id}/set-default. */
    public function setDefault(): array
    {
        if ($this->id === null) {
            return ['status' => false, 'message' => 'Config id is required.', 'http' => 0];
        }

        return $this->http()->patch("/api/v1/configurations/{$this->id}/set-default")->json() ?? [];
    }

    /** Remove a configuração. DELETE /api/v1/configurations/{id}. */
    public function delete(): array
    {
        if ($this->id === null) {
            return ['status' => false, 'message' => 'Config id is required to delete.', 'http' => 0];
        }

        return $this->http()->delete("/api/v1/configurations/{$this->id}")->json() ?? [];
    }

    /**
     * Aliases de canal desta configuração (apenas Telegram e Slack).
     * Nomes lógicos (ex.: "equipe") que o servidor resolve para o destino real
     * (chat_id no Telegram, ID/#canal no Slack) na hora do envio. Requer o id da config.
     *
     *   NotifyQuery::server()->telegram()->config('uuid')->channels()->all();
     *   NotifyQuery::server()->telegram()->config('uuid')->channels()->set('equipe', '-1003142488245');
     *   NotifyQuery::server()->slack()->config('uuid')->channels()->remove('equipe');
     */
    public function channels(): ServerChannelAlias
    {
        return new ServerChannelAlias($this->apiUrl, $this->apiKey, $this->id);
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}

// ──────────────────────────────────────────────────────────────────────────────

/**
 * Aliases de canal de uma configuração (Telegram/Slack) — nomes lógicos → destino real.
 * Obtido via NotifyQuery::server()->{telegram|slack}()->config('uuid')->channels().
 * Recurso: /api/v1/configurations/{id}/channels.
 *
 * No envio, basta mandar o nome lógico (->chatId('equipe') / ->channel('equipe')); se não
 * for um alias cadastrado, o valor é usado como está (fallthrough).
 */
class ServerChannelAlias
{
    public function __construct(
        protected string $apiUrl,
        protected string $apiKey,
        protected ?string $configId = null,
    ) {}

    /**
     * Lista os aliases da config (mapa nome => destino).
     * GET /api/v1/configurations/{id}/channels.
     */
    public function all(): array
    {
        if ($this->configId === null) {
            return [];
        }

        return $this->http()->get("/api/v1/configurations/{$this->configId}/channels")->json('data', []);
    }

    /**
     * Cria ou atualiza um alias (idempotente). POST /api/v1/configurations/{id}/channels.
     *
     * @param string $name    Nome lógico (slug: sem espaço/barra), ex.: "equipe".
     * @param string $target  Destino real (chat_id no Telegram; ID C0.. ou #canal no Slack).
     */
    public function set(string $name, string $target): array
    {
        if ($this->configId === null) {
            return ['status' => false, 'message' => 'Config id is required.', 'http' => 0];
        }

        return $this->http()->post("/api/v1/configurations/{$this->configId}/channels", [
            'name'   => $name,
            'target' => $target,
        ])->json() ?? [];
    }

    /**
     * Remove um alias. DELETE /api/v1/configurations/{id}/channels/{name}.
     * Retorna 404 (no http) se o name não existir.
     */
    public function remove(string $name): array
    {
        if ($this->configId === null) {
            return ['status' => false, 'message' => 'Config id is required.', 'http' => 0];
        }

        $response = $this->http()->delete("/api/v1/configurations/{$this->configId}/channels/{$name}");

        return ($response->json() ?? []) + ['http' => $response->status()];
    }

    protected function http()
    {
        return Http::withHeaders(['X-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->apiUrl);
    }
}
