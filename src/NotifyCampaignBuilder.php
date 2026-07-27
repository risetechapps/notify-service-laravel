<?php

namespace RiseTechApps\Notify;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RiseTechApps\Notify\Message\EmailTable;

/**
 * Fluent builder para campanhas de SMS e Email.
 *
 * ──────────────────────────────────────────────────────────────────────────────
 * SMS — array direto
 * ──────────────────────────────────────────────────────────────────────────────
 *   NotifyCampaignBuilder::sms()
 *       ->name('Promo Junho')
 *       ->content('Olá {name}, sua oferta: https://loja.com')   // SMS usa {chave} simples
 *       ->tag(['promo', 'junho'])
 *       ->contacts([
 *           ['phone' => '5511999887766', 'name' => 'João'],
 *           ['phone' => '5521988776655', 'name' => 'Maria', 'extra_data' => ['cupom' => 'JUN10']],
 *       ])
 *       ->webhookUrl(route('notify.webhook.campaign'))
 *       ->send();
 *
 * ──────────────────────────────────────────────────────────────────────────────
 * SMS — via query Eloquent (processa em chunks, não carrega tudo em memória)
 * ──────────────────────────────────────────────────────────────────────────────
 *   NotifyCampaignBuilder::sms()
 *       ->name('Promo Junho')
 *       ->content('Olá {name}, seu cupom é {cupom}!')
 *       ->fromQuery(
 *           User::where('active', true),       // Builder
 *           contactColumn: 'phone',            // coluna do telefone no banco
 *           nameColumn:    'name',             // coluna do nome (opcional)
 *           extraColumns:  ['cupom' => 'coupon_code'], // viram extra_data (placeholders)
 *       )
 *       ->send();
 *
 * ──────────────────────────────────────────────────────────────────────────────
 * Email — via Collection
 * ──────────────────────────────────────────────────────────────────────────────
 *   NotifyCampaignBuilder::email()
 *       ->name('Black Friday 2026')
 *       ->subject('Ofertas imperdíveis!')
 *       ->line('Confira os descontos de até 70%.')
 *       ->action('https://loja.com/bf', 'Ver ofertas')
 *       ->from('ofertas@loja.com', 'Loja')
 *       ->fromCollection($users, contactColumn: 'email', nameColumn: 'name')
 *       ->scheduledAt('2026-11-29 08:00:00')
 *       ->send();
 */
class NotifyCampaignBuilder
{
    protected string $channel = 'sms';

    // Compartilhados entre SMS e Email
    protected string $name = '';
    protected ?string $configId = null;
    protected ?string $webhookUrl = null;
    protected int $ratePerMinute = 60;
    protected ?string $scheduledAt = null;
    protected ?array $credentials = null;
    protected string|array|null $tag = null;

    // Contatos resolvidos (array final)
    protected array $contacts = [];

    // Fonte lazy (query) — processada só no send()
    protected ?Builder $querySource = null;
    protected string $queryContactColumn = 'email';
    protected ?string $queryNameColumn = null;
    protected array $queryExtraColumns = [];

    // Template SMS
    protected string $smsContent = '';

    // Template Email
    protected string $emailSubject = '';
    protected ?string $emailSubjectMessage = null;
    protected ?string $emailLine = null;
    protected array $emailLineHeader = [];
    protected array $emailLineFooter = [];
    protected array $emailAction = [];
    protected array $emailTables = [];
    protected array $emailLists = [];
    protected string $emailTheme = 'default';
    protected ?string $emailSignature = null;
    protected string $emailFrom = '';
    protected string $emailNameFrom = '';

    // ── Construtores estáticos ─────────────────────────────────────────────────

    public static function sms(): static
    {
        $instance = new static();
        $instance->channel = 'sms';
        return $instance;
    }

    public static function email(): static
    {
        $instance = new static();
        $instance->channel = 'email';
        return $instance;
    }

    // ── Campos compartilhados ─────────────────────────────────────────────────

    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function configId(string $configId): static
    {
        $this->configId = $configId;
        return $this;
    }

    public function webhookUrl(string $url): static
    {
        $this->webhookUrl = $url;
        return $this;
    }

    public function ratePerMinute(int $rate): static
    {
        $this->ratePerMinute = $rate;
        return $this;
    }

    public function scheduledAt(string $datetime): static
    {
        $this->scheduledAt = $datetime;
        return $this;
    }

    /**
     * Credenciais inline para este envio — sobrescreve a config padrão do servidor.
     * Use NotifyCredentials para montar o array correto para cada driver.
     *
     * Exemplo:
     *   ->credentials(NotifyCredentials::twilio('SID', 'TOKEN', '+15551234567'))
     *   ->credentials(NotifyCredentials::resend('re_xxxxxxxxxxxx'))
     */
    public function credentials(array $credentials): static
    {
        $this->credentials = $credentials;
        return $this;
    }

    /**
     * Tag(s) propagada(s) para cada notificação gerada pela campanha.
     * Aceita string única ou array de strings.
     */
    public function tag(string|array $tag): static
    {
        $this->tag = $tag;
        return $this;
    }

    // ── Fontes de contatos ────────────────────────────────────────────────────

    /**
     * Retorna a chave correta do contato conforme o canal: 'phone' (sms) ou 'email' (email).
     */
    protected function contactKey(): string
    {
        return $this->channel === 'sms' ? 'phone' : 'email';
    }

    /**
     * Contatos como array direto.
     *
     * SMS:   [['phone' => '5511999887766', 'name' => 'João'], ...]
     * Email: [['email' => 'joao@ex.com',   'name' => 'João'], ...]
     */
    public function contacts(array $contacts): static
    {
        // Aceita um único contato achatado: ['phone' => '...', 'name' => '...']
        // e normaliza para lista, evitando que array_map() itere sobre os valores.
        if ($contacts !== [] && !array_is_list($contacts)) {
            $contacts = [$contacts];
        }

        $this->contacts = $contacts;
        $this->querySource = null;
        return $this;
    }

    /**
     * Contatos via query Eloquent — processada em chunks no send().
     * Não carrega todos os registros em memória.
     *
     * @param Builder $query Query já montada (sem ->get())
     * @param string $contactColumn Coluna com telefone (sms) ou email (email)
     * @param string|null $nameColumn Coluna com o nome (opcional)
     * @param array $extraColumns Colunas extras viram `extra_data` (placeholders).
     *              Lista (['cupom']) usa o mesmo nome; mapa (['cupom' => 'coupon_code'])
     *              renomeia a chave do placeholder para o nome da coluna de origem.
     */
    public function fromQuery(Builder $query, string $contactColumn, ?string $nameColumn = null, array $extraColumns = []): static
    {
        $this->querySource = $query;
        $this->queryContactColumn = $contactColumn;
        $this->queryNameColumn = $nameColumn;
        $this->queryExtraColumns = $extraColumns;
        $this->contacts = [];
        return $this;
    }

    /**
     * Contatos via Collection.
     *
     * @param Collection $collection
     * @param string $contactColumn Atributo com telefone (sms) ou email (email)
     * @param string|null $nameColumn Atributo com o nome (opcional)
     * @param array $extraColumns Atributos extras viram `extra_data` (placeholders).
     *              Lista (['cupom']) usa o mesmo nome; mapa (['cupom' => 'coupon_code'])
     *              renomeia a chave do placeholder para o nome do atributo de origem.
     */
    public function fromCollection(Collection $collection, string $contactColumn, ?string $nameColumn = null, array $extraColumns = []): static
    {
        $key = $this->contactKey();

        $this->contacts = $collection->map(function ($item) use ($contactColumn, $nameColumn, $extraColumns, $key) {
            $row = [$key => data_get($item, $contactColumn)];

            if ($nameColumn) {
                $row['name'] = data_get($item, $nameColumn);
            }

            $extra = $this->extractExtraData($item, $extraColumns);

            if ($extra !== []) {
                $row['extra_data'] = $extra;
            }

            return $row;
        })->filter(fn($r) => !empty($r[$key]))->values()->all();

        $this->querySource = null;
        return $this;
    }

    /**
     * Extrai colunas/atributos extras de um registro para o array `extra_data`.
     * Chave numérica = usa o nome da coluna como chave do placeholder; chave string
     * = renomeia o placeholder. Valores nulos são ignorados.
     */
    protected function extractExtraData(mixed $item, array $extraColumns): array
    {
        $extra = [];

        foreach ($extraColumns as $placeholder => $column) {
            $outKey = is_int($placeholder) ? $column : $placeholder;
            $value = data_get($item, $column);

            if (!is_null($value)) {
                $extra[$outKey] = $value;
            }
        }

        return $extra;
    }

    // ── Campos de SMS ─────────────────────────────────────────────────────────

    public function content(string $content): static
    {
        $this->smsContent = $content;
        return $this;
    }

    public function from(string $from, string $nameFrom = ''): static
    {
        // Só email usa remetente; o servidor não aceita mais `from` em SMS.
        $this->emailFrom = $from;
        $this->emailNameFrom = $nameFrom;
        return $this;
    }

    // ── Campos de Email ───────────────────────────────────────────────────────

    public function subject(string $subject): static
    {
        $this->emailSubject = $subject;
        return $this;
    }

    public function subjectMessage(string $msg): static
    {
        $this->emailSubjectMessage = $msg;
        return $this;
    }

    public function line(string $line): static
    {
        $this->emailLine = $line;
        return $this;
    }

    public function lineHeader(string $line): static
    {
        $this->emailLineHeader[] = $line;
        return $this;
    }

    public function lineFooter(string $line): static
    {
        $this->emailLineFooter[] = $line;
        return $this;
    }

    public function action(string $url, string $text): static
    {
        $this->emailAction = ['url' => $url, 'text' => $text];
        return $this;
    }

    public function addTable(EmailTable|array $table): static
    {
        $this->emailTables[] = $table instanceof EmailTable ? $table->toArray() : $table;
        return $this;
    }

    public function addList(string $type, array $items): static
    {
        $this->emailLists[] = ['type' => $type, 'items' => $items];
        return $this;
    }

    public function theme(string $theme): static
    {
        $this->emailTheme = $theme;
        return $this;
    }

    public function signature(string $signature): static
    {
        $this->emailSignature = $signature;
        return $this;
    }

    // ── Envio ─────────────────────────────────────────────────────────────────

    /**
     * Dispara a campanha ao servidor. Não persiste nada localmente.
     *
     * @return array Resposta crua do servidor (campaign_id, status, ...) ou [] em falha.
     */
    public function send(): array
    {
        $contacts   = $this->resolveContacts();
        $template   = $this->buildTemplate();
        $webhookUrl = $this->webhookUrl ?: config('notify.webhook');

        return $this->dispatch($this->buildPayload($contacts, $template, $webhookUrl));
    }

    /**
     * Dispara uma campanha de email com template HTML cru.
     *
     * @return array Resposta crua do servidor ou [] em falha.
     */
    public function sendHtml(string $path): array
    {
        $contacts   = $this->resolveContacts();
        $template   = $this->buildTemplateHtml($path);
        $webhookUrl = $this->webhookUrl ?: config('notify.webhook');

        return $this->dispatch($this->buildPayload($contacts, $template, $webhookUrl));
    }

    /**
     * POST do payload para o endpoint de campanha do servidor.
     */
    protected function dispatch(array $payload): array
    {
        try {
            $endpoint = $this->channel === 'sms' ? 'sms' : 'mail';

            $response = Http::withHeaders(['X-API-KEY' => config('notify.key')])
                ->acceptJson()
                ->post(Notify::BASE_URL . "/api/v1/send/campaigns/{$endpoint}", $payload);

            if ($response->failed()) {
                throw new \Exception('Error creating campaign: ' . $response->body());
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            report($e);

            return [];
        }
    }

    // ── Internos ──────────────────────────────────────────────────────────────

    protected function resolveContacts(): array
    {
        // Se veio de fromQuery(), processa agora em chunks
        if ($this->querySource !== null) {
            $contacts = [];
            $contactKey = $this->contactKey();

            $this->querySource->chunk(500, function ($rows) use (&$contacts, $contactKey) {
                foreach ($rows as $row) {
                    $value = data_get($row, $this->queryContactColumn);

                    if (empty($value)) {
                        continue;
                    }

                    $contact = [$contactKey => $value];

                    if ($this->queryNameColumn) {
                        $contact['name'] = data_get($row, $this->queryNameColumn);
                    }

                    $extra = $this->extractExtraData($row, $this->queryExtraColumns);

                    if ($extra !== []) {
                        $contact['extra_data'] = $extra;
                    }

                    $contacts[] = $contact;
                }
            });

            return $contacts;
        }

        return $this->contacts;
    }

    protected function buildTemplate(): array
    {
        if ($this->channel === 'sms') {
            return array_filter([
                'content' => $this->smsContent,
            ], fn($v) => $v !== '' && !is_null($v));
        }

        // Email
        return array_filter([
            'email_from' => $this->emailFrom ?: config('notify.mail.from.address'),
            'name_from' => $this->emailNameFrom ?: config('notify.mail.from.name'),
            'subject' => $this->emailSubject,
            'subject_message' => $this->emailSubjectMessage,
            'theme' => $this->emailTheme,
            'app_name' => config('notify.mail.app_name', config('app.name')),
            'line' => $this->emailLine,
            'line_header' => $this->emailLineHeader ?: null,
            'line_footer' => $this->emailLineFooter ?: null,
            'action' => $this->emailAction ?: null,
            'tables' => $this->emailTables ?: null,
            'lists' => $this->emailLists ?: null,
            'signature' => $this->emailSignature,
        ], fn($v) => !is_null($v));
    }

    protected function buildTemplateHtml(string $html): array
    {
        // Email
        return array_filter([
            'email_from' => $this->emailFrom ?: config('notify.mail.from.address'),
            'name_from' => $this->emailNameFrom ?: config('notify.mail.from.name'),
            'subject' => $this->emailSubject,
            'subject_message' => $this->emailSubjectMessage,
            'app_name' => config('notify.mail.app_name', config('app.name')),
            'html_raw' => file_get_contents($html),
        ], fn($v) => !is_null($v));
    }

    protected function buildPayload(array $contacts, array $template, string $webhookUrl): array
    {
        return array_filter([
            'name' => $this->name,
            'template' => $template,
            'contacts' => $contacts,
            'config_id' => $this->configId,
            'credentials' => $this->credentials,
            'webhook_url' => $webhookUrl,
            'rate_per_minute' => $this->ratePerMinute !== 60 ? $this->ratePerMinute : null,
            'scheduled_at' => $this->buildScheduledAt(),
            'tag' => $this->tag,
        ], fn($v) => !is_null($v));
    }

    protected function buildScheduledAt(): ?string
    {
        if ($this->scheduledAt === null) {
            return null;
        }

        $timezone = config('app.timezone', 'UTC');

        return \Carbon\Carbon::parse($this->scheduledAt, $timezone)
            ->format('Y-m-d\TH:i:sP');
    }
}
