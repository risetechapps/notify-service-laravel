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
 *   NotifyQuery::server()->campaigns('mail', 'campaign-uuid')->cancel();      // cancelar
 *   NotifyQuery::server()->campaigns('mail', 'uuid')->contacts()->status('failed')->get(); // contatos
 *
 *   // Multi-canal
 *   NotifyQuery::multi([...])->send();
 */
class NotifyQuery
{
    /** Ponto de entrada para consultas no servidor. */
    public static function server(): ServerQuery
    {
        return new ServerQuery();
    }

    /**
     * Envia para múltiplos canais em uma única requisição.
     *
     * @param  array  $channels  Lista de canais, cada um com 'channel' e 'data'
     * @param  string|null  $webhookUrl  URL de callback global (opcional)
     * @return NotifyMultiBuilder
     *
     * @example
     * NotifyQuery::multi([
     *     ['channel' => 'sms',   'data' => ['to' => '+5511999999999', 'content' => 'Olá!']],
     *     ['channel' => 'email', 'data' => ['to' => 'user@ex.com',   'subject' => 'Teste']],
     * ])->send();
     */
    public static function multi(array $channels, ?string $webhookUrl = null): NotifyMultiBuilder
    {
        return new NotifyMultiBuilder($channels, $webhookUrl);
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

    public function sms(?string $id = null): ServerChannelQuery
    {
        return new ServerChannelQuery($this->apiUrl, $this->apiKey, 'sms', $id);
    }

    // ── E-mail ────────────────────────────────────────────────────────────────

    public function mail(?string $id = null): ServerChannelQuery
    {
        return new ServerChannelQuery($this->apiUrl, $this->apiKey, 'email', $id);
    }

    // ── Push ──────────────────────────────────────────────────────────────────

    public function push(?string $id = null): ServerChannelQuery
    {
        return new ServerChannelQuery($this->apiUrl, $this->apiKey, 'push', $id);
    }

    // ── APNs ────────────────────────────────────────────────────────────────────

    public function apns(?string $id = null): ServerChannelQuery
    {
        return new ServerChannelQuery($this->apiUrl, $this->apiKey, 'apns', $id);
    }

    // ── Telegram ────────────────────────────────────────────────────────────────

    public function telegram(?string $id = null): ServerChannelQuery
    {
        return new ServerChannelQuery($this->apiUrl, $this->apiKey, 'telegram', $id);
    }

    // ── Slack ─────────────────────────────────────────────────────────────────

    public function slack(?string $id = null): ServerChannelQuery
    {
        return new ServerChannelQuery($this->apiUrl, $this->apiKey, 'slack', $id);
    }

    // ── Discord ───────────────────────────────────────────────────────────────

    public function discord(?string $id = null): ServerChannelQuery
    {
        return new ServerChannelQuery($this->apiUrl, $this->apiKey, 'discord', $id);
    }

    // ── Teams ─────────────────────────────────────────────────────────────────

    public function teams(?string $id = null): ServerChannelQuery
    {
        return new ServerChannelQuery($this->apiUrl, $this->apiKey, 'teams', $id);
    }

    // ── WebSocket ─────────────────────────────────────────────────────────────

    public function websocket(?string $id = null): ServerChannelQuery
    {
        return new ServerChannelQuery($this->apiUrl, $this->apiKey, 'websocket', $id);
    }

    // ── Webhook ───────────────────────────────────────────────────────────────

    public function webhook(?string $id = null): ServerChannelQuery
    {
        return new ServerChannelQuery($this->apiUrl, $this->apiKey, 'webhook', $id);
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

    public function tag(string|array $tag): static
    {
        $this->params['tag'] = is_array($tag) ? implode(',', $tag) : $tag;
        return $this;
    }

    public function untagged(bool $untagged = true): static
    {
        $this->params['untagged'] = $untagged;
        return $this;
    }

    public function search(string $search): static
    {
        $this->params['search'] = $search;
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

            if (!is_array($decoded)) {
                throw new \InvalidArgumentException('credentialsJson(): invalid JSON string provided');
            }

            $json = $decoded;
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
