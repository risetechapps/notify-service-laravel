# Notify Service for Laravel

[![Laravel](https://img.shields.io/badge/Laravel-12.x-red?logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-blue?logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE.md)

Client package para o servidor [NotifyKit](https://notifykit.app.br). Integra **10 canais de notificação**, **campanhas em massa** e **consultas de status** via API do servidor. É um cliente puro — **não persiste nada localmente**; todo o estado vive no servidor.

---

## Índice

- [Instalação](#instalação)
- [Configuração](#configuração)
- [Canais disponíveis](#canais-disponíveis)
- [Notificações individuais](#notificações-individuais)
  - [SMS](#sms)
  - [Email](#email)
  - [Push FCM](#push-fcm)
  - [APNS Apple Push](#apns-apple-push)
  - [Telegram](#telegram)
  - [Slack](#slack)
  - [Discord](#discord)
  - [Teams](#teams)
  - [WebSocket](#websocket)
  - [Webhook](#webhook)
- [Envio multicanal](#envio-multicanal)
- [Credenciais por driver](#credenciais-por-driver)
  - [Credenciais inline](#credenciais-inline)
- [Campanhas em massa](#campanhas-em-massa)
  - [Campanha SMS](#campanha-sms)
  - [Campanha Email](#campanha-email)
  - [Template HTML próprio](#template-html-próprio)
  - [Validações antes do disparo](#validações-antes-do-disparo)
  - [Fontes de contatos](#fontes-de-contatos)
- [Estado e rastreamento](#estado-e-rastreamento)
- [Webhook receiver](#webhook-receiver)
- [Consultas de status](#consultas-de-status)
- [Eventos Laravel](#eventos-laravel)
- [Configurações de driver](#configurações-de-driver)

---

## Instalação

```bash
composer require risetechapps/notify-service-for-laravel
```

Publique a config:

```bash
php artisan vendor:publish --provider="RiseTechApps\Notify\NotifyServiceProvider" --tag="config"
composer dump-autoload
```

> O pacote **não persiste nada localmente** — não há migrations nem tabelas. Todo o
> histórico/estado fica no servidor; consulte via `NotifyQuery::server()` e reaja
> aos callbacks pelos eventos de webhook (veja [Webhook receiver](#webhook-receiver)).

---

## Configuração

Adicione as variáveis ao seu `.env`:

```env
NOTIFY_SERVICE_KEY=sua-api-key
NOTIFY_SERVICE_WEBHOOK=https://sua-app.com/notify/webhook

# Segredo usado para validar a assinatura dos callbacks recebidos (obrigatório)
NOTIFY_SERVICE_WEBHOOK_SECRET=seu-segredo

# Rotas automáticas de webhook (opcional)
NOTIFY_SERVICE_ROUTES=true
NOTIFY_SERVICE_ROUTES_PREFIX=notify
```

Arquivo `config/notify.php`:

```php
return [
    'key'     => env('NOTIFY_SERVICE_KEY', ''),
    'webhook' => env('NOTIFY_SERVICE_WEBHOOK', ''),

    // Registra automaticamente as rotas de webhook do package
    'routes'            => env('NOTIFY_SERVICE_ROUTES', true),
    'routes_prefix'     => env('NOTIFY_SERVICE_ROUTES_PREFIX', 'notify'),
    'routes_middleware' => ['api'],

    // Validação de assinatura dos callbacks recebidos
    'webhook_secret'    => env('NOTIFY_SERVICE_WEBHOOK_SECRET', ''),
    'webhook_verify'    => env('NOTIFY_SERVICE_WEBHOOK_VERIFY', true),
    'webhook_tolerance' => env('NOTIFY_SERVICE_WEBHOOK_TOLERANCE', 300),
];
```

Quando `routes = true`, o package registra automaticamente:

```
POST /notify/webhook           → recebe status de notificações individuais
POST /notify/webhook/campaign  → recebe status de campanhas
```

Ambas já vêm protegidas pela validação de assinatura HMAC — veja
[Webhook receiver](#webhook-receiver).

Para registrar manualmente (defina `routes = false`), aplique o middleware
`notify.signature`:

```php
// routes/api.php
use RiseTechApps\Notify\Http\Controllers\NotifyWebhookController;

Route::post('/notify/webhook',          [NotifyWebhookController::class, 'notification'])
    ->middleware('notify.signature');

Route::post('/notify/webhook/campaign', [NotifyWebhookController::class, 'campaign'])
    ->middleware('notify.signature');
```

---

## Canais disponíveis

| Canal | Drivers suportados | Método `via()` |
|---|---|---|
| SMS | Mobizon, ClickSend, Twilio | `notify.sms` |
| Email | SMTP, Mailgun, Resend, SendGrid, SES, Postmark | `notify.mail` |
| Push Android | FCM | `notify.push` |
| Push iOS | APNS | `notify.apns` |
| Telegram | Telegram Bot API | `notify.telegram` |
| Slack | Slack Webhooks | `notify.slack` |
| Discord | Discord Webhooks | `notify.discord` |
| Teams | Microsoft Teams Webhooks | `notify.teams` |
| WebSocket | Pusher | `notify.websocket` |
| Webhook | HTTP genérico | `notify.webhook` |

---

## Notificações individuais

Todas as notificações usam o sistema de notificações nativo do Laravel. Crie uma classe de notificação, declare o canal em `via()` e implemente o método correspondente.

O envio faz um POST ao servidor; **nada é persistido localmente**. Acompanhe o status
pelos [eventos](#eventos-laravel) e consulte o histórico via `NotifyQuery::server()`.

**Retorno do canal.** Em sucesso, o canal devolve o JSON do servidor (`202` com
`notification_id`). Em falha HTTP, devolve o **corpo de erro da API** (ex.: `errors` de
uma validação `422`); se a exceção for de transporte (timeout, DNS), devolve
`['error' => 'mensagem']`. Só retorna `null` quando não há destino
(`routeNotificationFor()` vazio) ou quando o método `toNotifyX()` não devolve a classe
de mensagem esperada. Toda falha também dispara `NotifyFailedEvent` e vai para o log.

---

### SMS

```php
use RiseTechApps\Notify\Message\NotifySms;

class PedidoConfirmado extends Notification
{
    public function via($notifiable): array
    {
        return ['notify.sms'];
    }

    public function toNotifySms($notifiable): NotifySms
    {
        return (new NotifySms)
            ->to($notifiable->phone)
            ->content('Seu pedido #1234 foi confirmado!')
            ->tag(['pedido', 'transacional'])             // opcional, agrupa/filtra
            ->configId('Twilio Principal')                // opcional, UUID ou label
            ->webhookUrl('https://sua-app.com/notify/webhook'); // opcional
    }
}
```

| Método | Descrição |
|---|---|
| `->to(string)` | Número destino, ex: `+5521981425950` (máx: 32 chars) |
| `->content(string)` | Texto do SMS. Máx: 160 chars (sanitizado GSM-7 no servidor) |
| `->tag(string\|array)` | Tag(s) para agrupar/filtrar. Acumula entre chamadas e é mesclada com as tags default do config |
| `->configId(string)` | Provedor/credencial específico (UUID ou label). Sobrescreve `notify.sms.config_id` |
| `->webhookUrl(string)` | URL de callback de status (sobrescreve o global do config) |

> O remetente **não** é mais definido aqui — o servidor usa a credencial do provedor
> (config default ou a indicada por `config_id`).

**Defaults no `config/notify.php`:**

```php
'sms' => [
    'config_id' => env('NOTIFY_SMS_CONFIG_ID'), // usado quando a notificação não passa ->configId()
    'tags'      => ['minha-app'],               // aplicadas a todo SMS, mescladas com as da notificação
],
```

Precedência: `->configId()` da notificação **sobrescreve** o `config_id` do config; as tags
da notificação são **somadas** às tags default do config (sem duplicar).

**Envio on-demand** (sem model, direto para um número):

```php
use Illuminate\Support\Facades\Notification;

Notification::route('sms', '+5511999998888')->notify(new PedidoConfirmado());
```

**Ciclo de vida completo do SMS:**

| Etapa | Como |
|---|---|
| Enviar | `$user->notify(...)` ou `Notification::route('sms', $numero)->notify(...)` |
| Acompanhar status | eventos `NotifySentEvent` (envio) e `NotifyWebhookEvent` (callbacks) — ver [Eventos](#eventos-laravel) |
| Listar histórico | `NotifyQuery::server()->sms()->tag('promo')->status('delivered')->get()` |
| Detalhe + timeline | `NotifyQuery::server()->sms('uuid')->get()` |
| Cancelar (se `created`) | `NotifyQuery::server()->sms('uuid')->cancel()` |

Detalhes desses métodos em [Consultas de status → SMS](#sms-endpoints-dedicados).

> **Agendamento:** o envio individual de SMS **não** suporta agendamento — para SMS
> agendado, use uma [campanha SMS](#campanha-sms) com `->scheduledAt(...)`.

---

### Email

```php
use RiseTechApps\Notify\Message\NotifyMail;

class BemVindo extends Notification
{
    public function via($notifiable): array
    {
        return ['notify.mail'];
    }

    public function toNotifyMail($notifiable): NotifyMail
    {
        return (new NotifyMail)
            ->to($notifiable->email, $notifiable->name)   // nome é obrigatório no servidor
            ->from('noreply@app.com', 'Minha App')        // ou defina os defaults no config
            ->subject('Bem-vindo!')
            ->line('Obrigado por se cadastrar.')
            ->action('https://app.com/dashboard', 'Acessar painel')
            ->setSignature('Equipe Minha App')
            ->tag(['onboarding', 'transacional'])         // opcional, agrupa/filtra
            ->configId('Resend Transacional');            // opcional, UUID ou label
    }
}
```

#### Destinatário e remetente (campos obrigatórios)

O servidor exige `email`, `name`, `email_from`, `name_from` e `app_name`. Resolva assim:

| Campo | Como preencher |
|---|---|
| `email` / `name` (destinatário) | `->to($email, $name)` **ou** faça `routeNotificationForMail()` devolver `[$email => $name]` |
| `email_from` / `name_from` (remetente) | `->from($email, $name)` **ou** os defaults `notify.mail.from.*` no config/`.env` |
| `app_name` | `->appName(...)` **ou** `notify.mail.app_name`; cai para `config('app.name')` |

> ⚠️ Se nenhum desses for definido, o servidor responde `422` com
> `email_from / name / name_from is required`. O `name` do destinatário **não** é
> deduzido automaticamente — defina-o via `->to()` ou no `routeNotificationForMail()`.

No seu `Notifiable`, para enviar o nome do destinatário junto:

```php
public function routeNotificationForMail(): array
{
    return [$this->email => $this->name];
}
```

#### Métodos

**Cabeçalho / remetente:**

| Método | Descrição |
|---|---|
| `->to(email, name?)` | Destinatário (*write-once*) |
| `->from(email, name?)` | Remetente (*write-once*; default em `notify.mail.from.*`) |
| `->appName(string)` | Nome do app/marca no template (default `notify.mail.app_name` → `config('app.name')`) |
| `->subject(string)` | Assunto (máx 255) |
| `->subjectMessage(string)` | Assunto detalhado; **tem prioridade sobre `subject`** no servidor (máx 1000) |
| `->theme(string)` | Tema do template. Default: `notify.mail.theme` (`default`) |

**Corpo:**

| Método | Descrição |
|---|---|
| `->line(string)` | Parágrafo principal (máx 1000) |
| `->lineHeader(string)` | Linha antes do corpo (acumula entre chamadas) |
| `->lineFooter(string)` | Linha após o corpo (acumula entre chamadas) |
| `->action(url, text)` | Botão CTA (a `url` é obrigatória quando há action) |
| `->addTable(EmailTable\|array)` | Adiciona uma tabela: `['headers' => [], 'rows' => [[]]]` |
| `->tables(array)` | **Substitui** todas as tabelas de uma vez (reseta as anteriores) |
| `->table(callable)` | Adiciona uma tabela via callback `EmailTable` fluente |
| `->addList(type, items)` | Adiciona uma lista: tipo `ordered` ou `unordered` |
| `->lists(array)` | Substitui todas as listas de uma vez |
| `->setSignature(string)` | Assinatura — acumula linha a linha |
| `->signature(string\|array)` | Define a assinatura inteira de uma vez |
| `->attachFromUrl(string\|array)` | Anexa arquivo(s) por URL (acumula) |

**Roteamento / rastreio:**

| Método | Descrição |
|---|---|
| `->tag(string\|array)` | Tag(s) para agrupar/filtrar. Acumula e mescla com `notify.mail.tags` |
| `->configId(string)` | Credencial específica (UUID ou label). Sobrescreve `notify.mail.config_id` |
| `->webhookUrl(string)` | URL de callback de status (sobrescreve o global do config) |

> ⚠️ `addTable()` seguido de `tables()` descarta a tabela do `addTable` — o `tables()`
> **reseta** a lista. Para múltiplas tabelas use `addTable()` repetido **ou** só `tables([...])`.

#### Defaults no `config/notify.php`

```php
'mail' => [
    'from' => [
        'address' => env('NOTIFY_MAIL_FROM_ADDRESS'),   // remetente default
        'name'    => env('NOTIFY_MAIL_FROM_NAME'),
    ],
    'app_name'  => env('NOTIFY_MAIL_APP_NAME'),          // cai para config('app.name')
    'theme'     => env('NOTIFY_MAIL_THEME', 'default'),
    'config_id' => env('NOTIFY_MAIL_CONFIG_ID'),         // usado quando não passa ->configId()
    'tags'      => [],                                   // mescladas com as da notificação
],
```

```dotenv
NOTIFY_MAIL_FROM_ADDRESS=no-reply@suaempresa.com
NOTIFY_MAIL_FROM_NAME="Sua Empresa"
NOTIFY_MAIL_APP_NAME=NotifyApp
NOTIFY_MAIL_CONFIG_ID=smtp-principal
```

Com os defaults configurados, o mínimo vira só `->to()` + `->subject()` + `->line()`.

> Se você **publicou** o `config/notify.php` antes da versão com o bloco `mail`, republique
> com `--force` (ou adicione o bloco manualmente), senão os defaults de e-mail não existem.

Precedência: `->configId()`/`->appName()`/`->from()` da notificação **sobrescrevem** os defaults;
as tags da notificação são **somadas** às `notify.mail.tags` (sem duplicar).

#### Envio on-demand (sem model)

```php
use Illuminate\Support\Facades\Notification;

Notification::route('mail', ['cliente@exemplo.com' => 'Maria Silva'])
    ->notify(new BemVindo());
```

#### Ciclo de vida completo do e-mail

| Etapa | Como |
|---|---|
| Enviar | `$user->notify(...)` ou `Notification::route('mail', [...])->notify(...)` |
| Acompanhar status | eventos `NotifySentEvent` e `NotifyWebhookEvent` — ver [Eventos](#eventos-laravel) |
| Listar histórico | `NotifyQuery::server()->mail()->tag('pedido')->status('delivered')->get()` |
| Detalhe + timeline | `NotifyQuery::server()->mail('uuid')->get()` |
| Cancelar (se `created`) | `NotifyQuery::server()->mail('uuid')->cancel()` |

Detalhes desses métodos em [Consultas de status → Email](#email-endpoints-dedicados).

---

### Push FCM

```php
use RiseTechApps\Notify\Message\NotifyPush;

public function toNotifyPush($notifiable): NotifyPush
{
    return (new NotifyPush)
        ->token($notifiable->fcm_token)   // string ou array de tokens
        ->title('Pedido a caminho 🚚')
        ->body('Seu pedido #123 saiu para entrega.')
        ->image('https://app.com/banner.png')
        ->channelId('pedidos')
        ->priority('high')
        ->ttl(600)
        ->link('https://app.com/pedidos/123')
        ->data(['route' => '/pedidos/123']);   // valores devem ser strings (regra FCM)
}
```

**Alvo** (use um dos três):

| Método | Descrição |
|---|---|
| `->token(string\|array)` | Device token(s) FCM. *Write-once*: o primeiro valor não-vazio prevalece |
| `->topic(string)` | Tópico FCM — todos os inscritos |
| `->condition(string)` | Combina tópicos, ex: `"'a' in topics && 'b' in topics"` |

**Conteúdo:**

| Método | Descrição |
|---|---|
| `->title(string)` | Título da notificação |
| `->body(string)` | Corpo da notificação |
| `->image(string)` | URL da imagem grande (`->imageUrl()` é alias) |
| `->icon(string)` | URL do ícone pequeno |
| `->data(array)` | Dados extras (todos os valores como string) |

> `title`/`body` são obrigatórios — **exceto** quando `silent(true)` (push data-only).

**Comportamento:**

| Método | Descrição |
|---|---|
| `->sound(string\|array)` | Nome do arquivo de som; ou objeto de som crítico iOS `['name' => 'alarm.caf', 'volume' => 1.0]` (atalho: `->criticalSound()`) |
| `->criticalSound(name, volume?)` | Som crítico no iOS (toca no modo silencioso). Gera `['name' => ..., 'volume' => ...]`; combine com `->interruptionLevel('critical')` |
| `->channelId(string)` | Android notification channel id (necessário p/ som/vibração no Android 8+) |
| `->badge(int)` | Número no badge (0 limpa) |
| `->priority(string)` | Entrega: `high` \| `normal` |
| `->ttl(int\|string)` | Time-to-live em segundos (`600`) ou string (`"3600s"`) |
| `->collapseKey(string)` | Agrupa/substitui notificações pela mesma chave |
| `->silent(bool?)` | Push data-only / background sync (sem alerta visível) |

**iOS / ações:**

| Método | Descrição |
|---|---|
| `->clickAction(string)` | Android intent action ao tocar |
| `->category(string)` | Categoria de action buttons no iOS |
| `->interruptionLevel(string)` | `passive` \| `active` \| `time-sensitive` \| `critical` |

**Heads-up / agrupamento / interação:**

| Método | Descrição |
|---|---|
| `->tag(string)` | Agrupa/substitui no device (Android+web) e etiqueta o histórico. **String única** (no push não é array); sobrescreve `notify.push.tag` |
| `->notificationPriority(string)` | Heads-up Android: `PRIORITY_HIGH` \| `PRIORITY_MAX` ... |
| `->requireInteraction(bool?)` | Fixa a notificação até o usuário interagir (web) |
| `->action(title, id, icon?)` | Adiciona um botão (máx 3). Acumula entre chamadas |
| `->actions(array)` | Define todos os botões de uma vez |
| `->line(string)` | Adiciona um item de lista InboxStyle (máx 10, via data). Acumula |
| `->lines(array)` | Define todos os itens de lista de uma vez |

**Extras:**

| Método | Descrição |
|---|---|
| `->link(string)` | Deep link / URL aberta ao tocar (web push) |
| `->analyticsLabel(string)` | Rótulo de analytics no Firebase |
| `->android(array)` | Override bruto do bloco `android` do FCM |
| `->apns(array)` | Override bruto do bloco `apns` do FCM |
| `->webpush(array)` | Override bruto do bloco `webpush` do FCM |
| `->configId(string)` | Credencial FCM (UUID ou label). Sobrescreve `notify.push.config_id` |
| `->webhookUrl(string)` | URL de callback de status (default: `notify.webhook` global) |

> ⚠️ No push, **`tag` é string única** (é a FCM grouping tag, regra de validação do
> servidor) — diferente de SMS/e-mail, aqui **não** aceita array.

**Defaults no `config/notify.php`:**

```php
'push' => [
    'config_id' => env('NOTIFY_PUSH_CONFIG_ID'), // usado quando não passa ->configId()
    'tag'       => env('NOTIFY_PUSH_TAG'),        // string única; sobrescrita por ->tag()
],
```

Exemplos avançados:

```php
// Som crítico no iOS (exige permissão de "critical alerts" no app)
(new NotifyPush)
    ->token($fcm)
    ->title('ALERTA')
    ->body('Temperatura acima do limite!')
    ->criticalSound('alarm.caf', 1.0)
    ->interruptionLevel('critical')
    ->priority('high');

// Tópico + imagem + heads-up + badge + tag de agrupamento
(new NotifyPush)
    ->topic('promocoes')
    ->title('Black Friday começou!')
    ->body('Até 70% OFF só hoje.')
    ->image('https://cdn.empresa.com/promo.jpg')
    ->channelId('ofertas')
    ->notificationPriority('PRIORITY_MAX')
    ->badge(1)
    ->tag('blackfriday');

// Botões + lista (InboxStyle) + deep link
(new NotifyPush)
    ->token($fcm)
    ->title('3 novas mensagens')
    ->body('Toque para ver')
    ->line('Ana: bom dia!')
    ->line('João: reunião 14h')
    ->line('Maria: enviei o arquivo')
    ->action('Responder', 'reply')
    ->action('Marcar como lida', 'mark_read')
    ->clickAction('OPEN_CHAT')
    ->data(['screen' => 'chat', 'chat_id' => '987']);

// Push silencioso / data-only
(new NotifyPush)->token($fcm)->silent()->data(['sync' => 'contacts', 'version' => '42']);

// Override bruto por plataforma
(new NotifyPush)
    ->token($fcm)->title('Custom')->body('Avançado')
    ->android(['notification' => ['color' => '#E53935', 'notification_priority' => 'PRIORITY_MAX']])
    ->apns(['payload' => ['aps' => ['thread-id' => 'grupo-pedidos']]]);
```

**Ciclo de vida completo do push:**

| Etapa | Como |
|---|---|
| Enviar | `$user->notify(...)` (token via `routeNotificationFor('push')` ou `->token()`) |
| Acompanhar status | eventos `NotifySentEvent` e `NotifyWebhookEvent` — ver [Eventos](#eventos-laravel) |
| Listar histórico | `NotifyQuery::server()->push()->tag('promo')->status('sent')->get()` |
| Detalhe + timeline | `NotifyQuery::server()->push('uuid')->get()` |
| Cancelar (se `created`) | `NotifyQuery::server()->push('uuid')->cancel()` |

Detalhes em [Consultas de status → Push](#push-endpoints-dedicados).

> **Tokens inválidos:** quando você envia para um array de tokens, o servidor reporta os
> mortos no webhook pelo evento `token_invalid` (campo `tokens: [...]` no payload do
> `NotifyWebhookEvent`) — use para limpar seu store.

---

### APNS Apple Push

Push direto para iOS via APNs. Aceita token único ou lista de tokens; o servidor enfileira
(resposta `202` com `notification_id`) e devolve o status pelo webhook.

```php
use RiseTechApps\Notify\Message\NotifyApns;

public function toNotifyApns($notifiable): NotifyApns
{
    return (new NotifyApns)
        ->token($notifiable->apns_token)   // string ou array de tokens
        ->title('Novo pedido')
        ->subtitle('Pedido #1234')
        ->body('Seu pedido foi enviado.')
        ->badge(1)
        ->sound('default')
        ->category('ORDER')
        ->threadId('pedidos')
        ->tag(['entregas', 'transacional'])  // opcional, agrupa/filtra
        ->configId('APNS Produção')          // opcional, UUID ou label
        ->data(['order_id' => '1234']);
}
```

| Método | Descrição |
|---|---|
| `->token(string\|array)` | Device token(s) Apple. *Write-once*: o primeiro valor não-vazio prevalece |
| `->title(string)` | Título |
| `->body(string)` | Corpo |
| `->subtitle(string)` | Subtítulo |
| `->badge(int)` | Número no badge do ícone (0 limpa) |
| `->sound(string)` | Nome do arquivo de som (ex.: `default`) |
| `->data(array)` | Payload customizado entregue ao app |
| `->category(string)` | Categoria para action buttons iOS |
| `->threadId(string)` | Agrupa notificações relacionadas no device |
| `->tag(string\|array)` | Tag(s) para agrupar/filtrar. Acumula e mescla com `notify.apns.tags` |
| `->configId(string)` | Credencial APNS (UUID ou label). Sobrescreve `notify.apns.config_id` |
| `->webhookUrl(string)` | URL de callback de status (default: `notify.webhook` global) |

**Defaults no `config/notify.php`:**

```php
'apns' => [
    'config_id' => env('NOTIFY_APNS_CONFIG_ID'), // usado quando não passa ->configId()
    'tags'      => [],                            // mescladas com as da notificação
],
```

Precedência: `->configId()` da notificação **sobrescreve** o `config_id` do config; as tags
da notificação são **somadas** às `notify.apns.tags` (sem duplicar).

**Envio on-demand** (sem model, direto para um token):

```php
use Illuminate\Support\Facades\Notification;

Notification::route('apns', $apnsToken)->notify(new PedidoEnviado());
```

**Ciclo de vida completo do APNs:**

| Etapa | Como |
|---|---|
| Enviar | `$user->notify(...)` (token via `routeNotificationFor('apns')` ou `->token()`) |
| Acompanhar status | eventos `NotifySentEvent` e `NotifyWebhookEvent` — ver [Eventos](#eventos-laravel) |
| Listar histórico | `NotifyQuery::server()->apns()->tag('entregas')->status('sent')->get()` |
| Detalhe + timeline | `NotifyQuery::server()->apns('uuid')->get()` |
| Cancelar (se `created`) | `NotifyQuery::server()->apns('uuid')->cancel()` |

Detalhes em [Consultas de status → APNS](#apns-endpoints-dedicados).

---

### Telegram

Envia texto, foto, localização, contato, enquete e botões inline. O servidor enfileira
(resposta `202` com `notification_id`) e devolve o status pelo webhook. Cada envio carrega
**um** tipo de conteúdo.

```php
use RiseTechApps\Notify\Message\NotifyTelegram;

class DeployConcluido extends Notification
{
    public function via($notifiable): array
    {
        return ['notify.telegram'];
    }

    public function toNotifyTelegram($notifiable): NotifyTelegram
    {
        return (new NotifyTelegram)
            ->chatId($notifiable->telegram_chat_id)
            ->message('🚀 Seu deploy foi concluído com sucesso!')
            ->parseMode('Markdown')
            ->disablePreview()
            ->button('Ver logs', 'https://app.com/logs');
    }
}
```

O `chat_id` também pode vir do `routeNotificationForTelegram()` no seu Notifiable:

```php
public function routeNotificationForTelegram(): string
{
    return $this->telegram_chat_id;
}
```

**Conteúdo** (use um por envio):

| Método | Descrição |
|---|---|
| `->message(string)` | Texto da mensagem. Máx: 4096 chars |
| `->imageUrl(string)` | Envia uma foto a partir de uma URL (o `message` vira legenda) |
| `->location(lat, lng)` | Envia uma localização (pino no mapa) |
| `->contact(phone, firstName, lastName?)` | Envia um cartão de contato |
| `->poll(question, options[], isAnonymous?)` | Envia uma enquete (`isAnonymous` default `true`) |

**Alvo / formatação:**

| Método | Descrição |
|---|---|
| `->chatId(string)` | ID numérico, @username **ou alias de canal** (ex.: `equipe`) (obrigatório). *Write-once* |
| `->parseMode(string)` | `Markdown` \| `MarkdownV2` \| `HTML`. Default: `Markdown` |
| `->button(text, url)` | Botão inline (cada chamada = uma linha) |
| `->buttons(array)` | Grade completa: `[[['text' => '', 'url' => '']]]` (substitui) |

**Opções comuns** (combinam com qualquer conteúdo):

| Método | Descrição |
|---|---|
| `->silent(bool?)` | Envio silencioso (`disable_notification`) |
| `->protectContent(bool?)` | Impede encaminhamento/cópia |
| `->replyTo(int)` | Responde a uma mensagem (message_id do Telegram) |
| `->threadId(int)` | Envia para um tópico de grupo/fórum |
| `->disablePreview(bool?)` | Desabilita preview de links |
| `->tag(string\|array)` | Tag(s) para agrupar/filtrar. Acumula e mescla com `notify.telegram.tags` |
| `->configId(string)` | Bot/credencial (UUID ou label). Sobrescreve `notify.telegram.config_id` |
| `->webhookUrl(string)` | URL de callback de status (default: `notify.webhook` global) |

**Defaults no `config/notify.php`:**

```php
'telegram' => [
    'config_id' => env('NOTIFY_TELEGRAM_CONFIG_ID'), // usado quando não passa ->configId()
    'tags'      => [],                                // mescladas com as da notificação
],
```

Precedência: `->configId()` da notificação **sobrescreve** o `config_id` do config; as tags
da notificação são **somadas** às `notify.telegram.tags` (sem duplicar).

#### Exemplos por tipo de conteúdo

```php
use RiseTechApps\Notify\Message\NotifyTelegram;

// Texto simples
(new NotifyTelegram)->chatId($chatId)->message('Olá! 👋');

// Texto com formatação HTML
(new NotifyTelegram)
    ->chatId($chatId)
    ->message('<b>Pedido #123</b> confirmado!')
    ->parseMode('HTML');

// Foto a partir de URL (message vira a legenda)
(new NotifyTelegram)
    ->chatId($chatId)
    ->imageUrl('https://cdn.app.com/banner.jpg')
    ->message('Confira a novidade!');

// Localização (pino no mapa)
(new NotifyTelegram)->chatId($chatId)->location(-23.5614, -46.6559);

// Contato (lastName é opcional)
(new NotifyTelegram)->chatId($chatId)->contact('+5511999998888', 'Suporte', 'NotifyApp');

// Enquete anônima (padrão)
(new NotifyTelegram)->chatId($chatId)->poll('Gostou?', ['Sim', 'Não']);

// Enquete não-anônima
(new NotifyTelegram)->chatId($chatId)->poll('Qual prefere?', ['A', 'B', 'C'], isAnonymous: false);
```

#### Exemplos de botões inline

```php
// Um botão por linha (cada ->button() vira uma nova linha)
(new NotifyTelegram)
    ->chatId($chatId)
    ->message('Escolha uma ação:')
    ->button('Ver pedido', 'https://app.com/pedidos/1')
    ->button('Falar com suporte', 'https://app.com/suporte');

// Grade completa: array externo = linhas, interno = botões na mesma linha
(new NotifyTelegram)
    ->chatId($chatId)
    ->message('Confirma?')
    ->buttons([
        [
            ['text' => '✅ Sim', 'url' => 'https://app.com/sim'],
            ['text' => '❌ Não', 'url' => 'https://app.com/nao'],
        ],
        [
            ['text' => 'Ver detalhes', 'url' => 'https://app.com/detalhes'],
        ],
    ]);
```

#### Exemplos de opções comuns

```php
// Silencioso + protegido contra encaminhamento/cópia
(new NotifyTelegram)
    ->chatId($chatId)
    ->message('Aviso confidencial')
    ->silent()
    ->protectContent();

// Responder a uma mensagem + enviar para um tópico de grupo/fórum
(new NotifyTelegram)
    ->chatId($grupoId)
    ->message('Respondendo...')
    ->replyTo(4521)        // message_id do Telegram da mensagem original
    ->threadId(12);        // tópico do fórum

// Tag + bot/credencial específico + webhook próprio
(new NotifyTelegram)
    ->chatId($chatId)
    ->message('Pedido enviado')
    ->tag(['pedidos', 'transacional'])
    ->configId('Bot Vendas')
    ->webhookUrl('https://sua-app.com/notify/webhook');
```

#### Envio on-demand (sem model)

```php
use Illuminate\Support\Facades\Notification;

Notification::route('telegram', '123456789')->notify(new DeployConcluido());
```

#### Gerenciar mensagens enviadas

Edite, apague ou fixe uma mensagem já enviada pelo próprio recurso de consulta,
em `NotifyQuery::server()->telegram($notificationId)`. Essas operações são **síncronas**
e usam apenas o **`notification_id`** — o UUID retornado no envio (`202`). O servidor
resolve o `chat_id` + `message_id` salvos, então você **não precisa mais** capturar o
`message_id` do Telegram no webhook.

O `notification_id` vem direto na resposta do envio, no `NotifySentEvent`:

```php
use RiseTechApps\Notify\Events\NotifySentEvent;

public function handle(NotifySentEvent $event): void
{
    if ($event->channel === 'telegram') {
        $notificationId = $event->response['notification_id'] ?? null;
        // guarde onde quiser, se for precisar editar/fixar/apagar depois
    }
}
```

```php
use RiseTechApps\Notify\NotifyQuery;

// Editar o texto (com parse mode opcional)
NotifyQuery::server()->telegram($notificationId)->edit('Texto *editado* ✏️');
NotifyQuery::server()->telegram($notificationId)->edit('<b>Novo</b>', 'HTML');

// Editar a legenda de uma mídia
NotifyQuery::server()->telegram($notificationId)->editCaption('Nova legenda');

// Apagar
NotifyQuery::server()->telegram($notificationId)->delete();

// Fixar (opcionalmente sem notificar os membros)
NotifyQuery::server()->telegram($notificationId)->pin(disableNotification: true);

// Todas retornam: ['status' => bool, 'message' => string|null, 'http' => int]
```

**Ciclo de vida completo do Telegram:**

| Etapa | Como |
|---|---|
| Enviar | `$user->notify(...)` ou `Notification::route('telegram', $chatId)->notify(...)` |
| Acompanhar status | eventos `NotifySentEvent` e `NotifyWebhookEvent` — ver [Eventos](#eventos-laravel) |
| Listar histórico | `NotifyQuery::server()->telegram()->tag('pedidos')->status('sent')->get()` |
| Detalhe + timeline | `NotifyQuery::server()->telegram('uuid')->get()` |
| Cancelar (se `created`) | `NotifyQuery::server()->telegram('uuid')->cancel()` |
| Editar/apagar/fixar | `NotifyQuery::server()->telegram($notificationId)->edit(...)` etc. |

Detalhes das consultas em [Consultas de status → Telegram](#telegram-endpoints-dedicados).

---

### Slack

Funciona em dois modos, conforme a credencial no servidor: **Bot Token** (entrega no
`channel` informado) ou **Incoming Webhook** (entrega numa URL de webhook do Slack).
O servidor enfileira (resposta `202` com `notification_id`) e devolve o status pelo webhook.

```php
use RiseTechApps\Notify\Message\NotifySlack;

class FalhaPagamento extends Notification
{
    public function via($notifiable): array
    {
        return ['notify.slack'];
    }

    public function toNotifySlack($notifiable): NotifySlack
    {
        return (new NotifySlack)
            ->channel('#alertas')
            ->title('Erro crítico')
            ->message('Ocorreu um erro na integração de pagamento.')
            ->color('#FF0000')
            ->field('Ambiente', 'Produção')
            ->field('Servidor', 'api-01')
            ->tag('alertas');               // opcional, agrupa/filtra
    }
}
```

**Conteúdo:**

| Método | Descrição |
|---|---|
| `->message(string)` | Texto principal. Máx: 4000 chars (obrigatório, salvo com `blocks` ou arquivo) |
| `->channel(string)` | Canal destino (Bot Token), ex: `#alerts`, `C01234ABC` **ou alias** (ex.: `equipe`) |
| `->title(string)` | Título do attachment (ativa o card rico) |
| `->color(string)` | Cor hex da barra lateral: `#FF0000` |
| `->footer(string)` | Rodapé do attachment |
| `->field(label, value, short?)` | Campo chave-valor no attachment (`short` = lado a lado) |
| `->fields(array)` | Define todos os campos de uma vez (substitui) |
| `->blocks(array)` | Block Kit cru (seções, botões, dividers, imagens). `message` vira fallback |

**Threads / menções:**

| Método | Descrição |
|---|---|
| `->thread(string)` | Responde em thread (o `ts` da mensagem-pai) |
| `->mentions(string\|array)` | Menções (acumula): `U123` (user), `C123` (canal), `channel`, `here` |

**Aparência:**

| Método | Descrição |
|---|---|
| `->username(string)` | Sobrescreve o nome do remetente |
| `->iconEmoji(string)` | Ícone por emoji, ex: `:rocket:` |
| `->iconUrl(string)` | Ícone por URL |

**Arquivo (Bot Token):**

| Método | Descrição |
|---|---|
| `->file(url, title?, filename?)` | Anexa um arquivo por URL |

**Transporte / servidor:**

| Método | Descrição |
|---|---|
| `->slackWebhookUrl(string)` | Força o modo Incoming Webhook (canal fixo; o `channel` é ignorado) |
| `->tag(string\|array)` | Tag(s) para agrupar/filtrar. Acumula e mescla com `notify.slack.tags` |
| `->configId(string)` | Credencial Slack (UUID ou label). Sobrescreve `notify.slack.config_id` |
| `->webhookUrl(string)` | URL de callback **de status** (default: `notify.webhook` global) |

> ⚠️ Não confunda `->slackWebhookUrl()` (destino de entrega no Slack, modo Incoming
> Webhook) com `->webhookUrl()` (callback de status que o **seu** app recebe).

**Modos de corpo** (prioridade no servidor): **1)** `blocks` (Block Kit) → `message` vira
fallback · **2)** attachment (quando há `title`/`fields`) → card colorido · **3)** texto simples
(só `message`).

**Modos de transporte:** **Bot Token** (`chat.postMessage`) — qualquer canal, habilita
thread, upload e editar/apagar · **Incoming Webhook** (`->slackWebhookUrl()`) — canal fixo,
mais simples.

**Defaults no `config/notify.php`:**

```php
'slack' => [
    'config_id' => env('NOTIFY_SLACK_CONFIG_ID'), // usado quando não passa ->configId()
    'tags'      => [],                            // mescladas com as da notificação
],
```

Precedência: `->configId()` da notificação **sobrescreve** o `config_id` do config; as tags
da notificação são **somadas** às `notify.slack.tags` (sem duplicar).

#### Formas de disparo

Toda mensagem Slack é construída com `NotifySlack` e enviada pelo sistema de notificações
do Laravel. Há três formas:

```php
use Illuminate\Support\Facades\Notification;

// 1. A partir de um model Notifiable (canal vem do routeNotificationForSlack(), se houver)
$user->notify(new FalhaPagamento());

// 2. On-demand, sem model — o alvo só é usado se a mensagem não trouxer ->channel()/->slackWebhookUrl()
Notification::route('slack', '#deploys')->notify(new FalhaPagamento());
```

Para **testar rapidamente** qualquer corpo de mensagem, crie uma notification genérica que
recebe um `NotifySlack` pronto:

```php
namespace App\Notifications;

use Illuminate\Notifications\Notification;
use RiseTechApps\Notify\Message\NotifySlack;

class TestSlack extends Notification
{
    public function __construct(protected NotifySlack $slack) {}

    public function via($notifiable): array
    {
        return ['notify.slack'];
    }

    public function toNotifySlack($notifiable): NotifySlack
    {
        return $this->slack;
    }
}
```

```php
use Illuminate\Support\Facades\Notification;
use App\Notifications\TestSlack;
use RiseTechApps\Notify\Message\NotifySlack;

// 3. Dispare qualquer um dos exemplos abaixo:
Notification::route('slack', '#deploys')->notify(new TestSlack(
    (new NotifySlack)->channel('#deploys')->message('Teste 🚀')
));
```

#### Exemplos por possibilidade

Cada bloco abaixo é o `NotifySlack` que você retorna no `toNotifySlack()` (ou passa para a
`TestSlack` acima):

```php
use RiseTechApps\Notify\Message\NotifySlack;

// Texto simples
(new NotifySlack)->channel('#deploys')->message('Deploy concluído 🚀')->tag('deploys');

// Attachment com campos (short = lado a lado)
(new NotifySlack)
    ->channel('#alertas')
    ->title('CPU 90%')
    ->message('Alerta')
    ->color('#FF0000')
    ->field('Servidor', 'web-01', short: true)
    ->footer('Monitoramento');

// Block Kit (botões)
(new NotifySlack)
    ->channel('#deploys')
    ->message('Deploy v1.2.0')   // fallback
    ->blocks([
        ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '*Deploy v1.2.0* :rocket:']],
        ['type' => 'divider'],
        ['type' => 'actions', 'elements' => [
            ['type' => 'button', 'text' => ['type' => 'plain_text', 'text' => 'Aprovar'],
             'style' => 'primary', 'url' => 'https://ci/482'],
        ]],
    ]);

// Menções
(new NotifySlack)->channel('#incidentes')->message('Incidente aberto.')->mentions(['here', 'U024BE7LH']);

// Thread (responde à mensagem-pai pelo ts)
(new NotifySlack)->channel('#deploys')->message('Deploy concluído ✅')->thread('1718150400.123456');

// Incoming Webhook + aparência (channel é ignorado)
(new NotifySlack)
    ->message('Backup ok.')
    ->slackWebhookUrl('https://hooks.slack.com/services/T/B/x')
    ->username('Backup Bot')
    ->iconEmoji(':floppy_disk:');

// Upload de arquivo (Bot Token)
(new NotifySlack)
    ->channel('#relatorios')
    ->message('Relatório em anexo:')
    ->file('https://storage.empresa.com/maio.pdf', 'Maio/2026');

// Aparência com ícone por URL
(new NotifySlack)
    ->channel('#geral')
    ->message('Olá do bot 👋')
    ->username('Status Bot')
    ->iconUrl('https://app.com/bot-avatar.png');

// Credencial específica (multi-tenant) + múltiplas tags
(new NotifySlack)
    ->channel('#vendas')
    ->message('Nova venda registrada')
    ->configId('Slack Workspace Vendas')   // UUID ou label
    ->tag(['vendas', 'transacional']);

// Callback de status próprio deste envio (≠ slackWebhookUrl)
(new NotifySlack)
    ->channel('#deploys')
    ->message('Pipeline iniciado')
    ->webhookUrl('https://sua-app.com/notify/webhook');
```

O canal destino também pode vir do `routeNotificationForSlack()` no seu Notifiable — ele
é usado **apenas** quando a mensagem não traz `->channel()` nem `->slackWebhookUrl()`:

```php
public function routeNotificationForSlack(): string
{
    return '#minha-equipe';
}
```

**Envio on-demand** (sem model):

```php
use Illuminate\Support\Facades\Notification;

Notification::route('slack', '#deploys')->notify(new FalhaPagamento());
```

#### Gerenciar mensagens enviadas (só Bot Token)

Edite ou apague uma mensagem já enviada pelo recurso de consulta, em
`NotifyQuery::server()->slack($notificationId)`. São operações **síncronas** e funcionam
**apenas no modo Bot Token** (Incoming Webhook não permite editar/apagar). O
`notification_id` vem na resposta do envio (`NotifySentEvent->response['notification_id']`).

```php
use RiseTechApps\Notify\NotifyQuery;

// Editar (chat.update)
NotifyQuery::server()->slack($notificationId)->edit('Deploy *concluído* ✅');

// Apagar (chat.delete)
NotifyQuery::server()->slack($notificationId)->delete();

// Ambas retornam: ['status' => bool, 'message' => string|null, 'http' => int]
```

**Ciclo de vida completo do Slack:**

| Etapa | Como |
|---|---|
| Enviar | `$user->notify(...)` ou `Notification::route('slack', $canal)->notify(...)` |
| Acompanhar status | eventos `NotifySentEvent` e `NotifyWebhookEvent` — ver [Eventos](#eventos-laravel) |
| Listar histórico | `NotifyQuery::server()->slack()->tag('deploys')->status('sent')->get()` |
| Detalhe + timeline | `NotifyQuery::server()->slack('uuid')->get()` |
| Cancelar (se `created`) | `NotifyQuery::server()->slack('uuid')->cancel()` |
| Editar/apagar (Bot Token) | `NotifyQuery::server()->slack($notificationId)->edit(...)` / `->delete()` |

Detalhes em [Consultas de status → Slack](#slack-endpoints-dedicados).

---

### Discord

Envia texto simples ou embed rico (título, cor, campos, imagem, thumbnail, rodapé).
O servidor enfileira (resposta `202` com `notification_id`) e devolve o status pelo webhook.

```php
use RiseTechApps\Notify\Message\NotifyDiscord;

class NovoCadastro extends Notification
{
    public function via($notifiable): array
    {
        return ['notify.discord'];
    }

    public function toNotifyDiscord($notifiable): NotifyDiscord
    {
        return (new NotifyDiscord)
            ->username('NotifyBot')
            ->title('Novo usuário cadastrado')
            ->message('João Silva acabou de se cadastrar.')
            ->color(0x2ecc71)               // verde (hex) — ou 3066993 em decimal
            ->field('Email', 'joao@email.com')
            ->field('Plano', 'Pro', inline: true)
            ->tag('cadastros');             // opcional, agrupa/filtra
    }
}
```

**Destino** (precedência: `discordWebhookUrl` › `channel` alias › webhook padrão da config):

| Método | Descrição |
|---|---|
| `->channel(string)` | Alias do canal (ex.: `equipe`); o servidor resolve para a webhook URL |
| `->discordWebhookUrl(string)` | Webhook URL direta (override; ver em Transporte) |

**Conteúdo / embed:**

| Método | Descrição |
|---|---|
| `->message(string)` | Texto da mensagem. Máx: 2000 chars (obrigatório, salvo com `embeds`/arquivo) |
| `->username(string)` | Sobrescreve o nome do webhook. Máx: 80 chars |
| `->avatarUrl(string)` | Sobrescreve o avatar do webhook (URL) |
| `->title(string)` | Título do embed (atalho). Máx: 256 chars |
| `->color(int)` | Cor do embed em decimal/hex, ex: `0xe74c3c` (vermelho) |
| `->imageUrl(string)` | Imagem grande no embed |
| `->thumbnail(string)` | Miniatura no embed |
| `->footer(string)` | Rodapé do embed. Máx: 2048 chars |
| `->field(label, value, inline?)` | Campo do embed (`inline` default `true`; máx 25) |
| `->fields(array)` | Define todos os campos de uma vez (substitui) |
| `->embeds(array)` | Embeds crus do Discord (máx 10). Têm **prioridade** sobre o embed-atalho |

**Menções:**

| Método | Descrição |
|---|---|
| `->mentions(string\|array)` | Menções (acumula): `"123"` (user), `"&456"` (cargo/role), `"everyone"`, `"here"` |
| `->allowedMentions(array)` | Override cru do `allowed_mentions` nativo (sobrescreve o derivado de `mentions`) |

**Thread / extras:**

| Método | Descrição |
|---|---|
| `->threadId(string)` | Posta numa thread já existente (canal de texto) |
| `->threadName(string)` | Cria uma thread nova (só em canais de fórum/mídia). Máx: 100 chars |
| `->tts(bool?)` | Text-to-speech |
| `->file(url, filename?)` | Anexa um arquivo por URL (o servidor baixa e faz o upload) |

**Transporte / servidor:**

| Método | Descrição |
|---|---|
| `->discordWebhookUrl(string)` | Webhook de destino do Discord (override da config) |
| `->tag(string\|array)` | Tag(s) para agrupar/filtrar. Acumula e mescla com `notify.discord.tags` |
| `->configId(string)` | Credencial Discord (UUID ou label). Sobrescreve `notify.discord.config_id` |
| `->webhookUrl(string)` | URL de callback **de status** (default: `notify.webhook` global) |

> ⚠️ Não confunda `->discordWebhookUrl()` (destino de entrega no Discord) com
> `->webhookUrl()` (callback de status que o **seu** app recebe). O card rico do Discord
> são `embeds` (≠ do Slack, que usa `blocks`); a cor é `int` decimal/hex; menção de cargo
> usa o prefixo `&`.

**Defaults no `config/notify.php`:**

```php
'discord' => [
    'config_id' => env('NOTIFY_DISCORD_CONFIG_ID'), // usado quando não passa ->configId()
    'tags'      => [],                              // mescladas com as da notificação
],
```

Precedência: `->configId()` da notificação **sobrescreve** o `config_id` do config; as tags
da notificação são **somadas** às `notify.discord.tags` (sem duplicar).

#### Formas de disparo

Toda mensagem Discord é construída com `NotifyDiscord` e enviada pelo sistema de
notificações do Laravel. Há três formas:

```php
use Illuminate\Support\Facades\Notification;

// 1. A partir de um model Notifiable (destino vem do routeNotificationForDiscord(), se houver)
$user->notify(new NovoCadastro());

// 2. On-demand, sem model
Notification::route('discord', $webhookOuId)->notify(new NovoCadastro());
```

```php
public function routeNotificationForDiscord(): string
{
    return 'https://discord.com/api/webhooks/123/abc'; // ou um id/label de config
}
```

Para **testar rapidamente** qualquer corpo de mensagem, crie uma notification genérica que
recebe um `NotifyDiscord` pronto:

```php
namespace App\Notifications;

use Illuminate\Notifications\Notification;
use RiseTechApps\Notify\Message\NotifyDiscord;

class TestDiscord extends Notification
{
    public function __construct(protected NotifyDiscord $discord) {}

    public function via($notifiable): array
    {
        return ['notify.discord'];
    }

    public function toNotifyDiscord($notifiable): NotifyDiscord
    {
        return $this->discord;
    }
}
```

```php
use Illuminate\Support\Facades\Notification;
use App\Notifications\TestDiscord;
use RiseTechApps\Notify\Message\NotifyDiscord;

// Dispare qualquer um dos exemplos abaixo:
Notification::route('discord', 'default')->notify(new TestDiscord(
    (new NotifyDiscord)->message('Teste 🚀')
));
```

#### Exemplos por possibilidade

Cada bloco abaixo é o `NotifyDiscord` que você retorna no `toNotifyDiscord()` (ou passa
para a `TestDiscord` acima):

```php
use RiseTechApps\Notify\Message\NotifyDiscord;

// Texto simples
(new NotifyDiscord)->message('Novo cadastro: João')->tag('cadastros');

// Por alias de canal (cadastrado na config — o servidor resolve a webhook URL)
(new NotifyDiscord)->channel('equipe')->message('Deploy concluído ✅');

// Embed completo (atalho)
(new NotifyDiscord)
    ->username('Status Bot')
    ->avatarUrl('https://app.com/bot.png')
    ->title('Build #482')
    ->message('Pipeline finalizado com sucesso.')
    ->color(0x2ecc71)
    ->field('Branch', 'main', inline: true)
    ->field('Duração', '3m12s', inline: true)
    ->thumbnail('https://cdn.app.com/ok.png')
    ->footer('CI/CD');

// Menções (role usa prefixo &)
(new NotifyDiscord)
    ->message('Incidente aberto.')
    ->mentions(['here', '&987654321', '123456789']);

// Controle fino das menções (allowed_mentions cru)
(new NotifyDiscord)
    ->message('Aviso geral')
    ->allowedMentions(['parse' => ['everyone'], 'users' => ['123'], 'roles' => ['456']]);

// Postar numa thread existente
(new NotifyDiscord)->message('Atualização: mitigado.')->threadId('1112223334445556667');

// Criar thread nova (canal de fórum)
(new NotifyDiscord)->message('Nova discussão')->threadName('Deploy 2026-06');

// Upload de arquivo por URL (o servidor baixa e envia)
(new NotifyDiscord)
    ->message('Log em anexo:')
    ->file('https://storage.empresa.com/erro.log', 'erro.log');

// Embeds crus (prioridade sobre o atalho)
(new NotifyDiscord)
    ->message('Resumo')
    ->embeds([
        ['title' => 'Embed 1', 'description' => 'Linha', 'color' => 0x3498db],
        ['title' => 'Embed 2', 'description' => 'Outra'],
    ]);

// Webhook de destino específico + TTS
(new NotifyDiscord)
    ->message('Mensagem falada')
    ->tts()
    ->discordWebhookUrl('https://discord.com/api/webhooks/123/abc');
```

#### Gerenciar mensagens enviadas

Edite ou apague uma mensagem já enviada pelo recurso de consulta, em
`NotifyQuery::server()->discord($notificationId)`. São operações **síncronas** que usam
apenas o `notification_id` (vem na resposta do envio, `NotifySentEvent->response['notification_id']`).

```php
use RiseTechApps\Notify\NotifyQuery;

// Editar (PATCH)
NotifyQuery::server()->discord($notificationId)->edit('Incidente resolvido ✅');

// Apagar (DELETE)
NotifyQuery::server()->discord($notificationId)->delete();

// Ambas retornam: ['status' => bool, 'message' => string|null, 'http' => int]
```

**Ciclo de vida completo do Discord:**

| Etapa | Como |
|---|---|
| Enviar | `$user->notify(...)` ou `Notification::route('discord', $alvo)->notify(...)` |
| Acompanhar status | eventos `NotifySentEvent` e `NotifyWebhookEvent` — ver [Eventos](#eventos-laravel) |
| Listar histórico | `NotifyQuery::server()->discord()->tag('cadastros')->status('sent')->get()` |
| Detalhe + timeline | `NotifyQuery::server()->discord('uuid')->get()` |
| Cancelar (se `created`) | `NotifyQuery::server()->discord('uuid')->cancel()` |
| Editar/apagar | `NotifyQuery::server()->discord($notificationId)->edit(...)` / `->delete()` |

Detalhes em [Consultas de status → Discord](#discord-endpoints-dedicados).

---

### Teams

Monta um card do Microsoft Teams (título, subtítulo, cor, imagem, facts e botões) — ou um
card cru via `->card()`. O servidor enfileira (resposta `202` com `notification_id`) e
devolve o status pelo webhook.

```php
use RiseTechApps\Notify\Message\NotifyTeams;

class RelatorioSemanal extends Notification
{
    public function via($notifiable): array
    {
        return ['notify.teams'];
    }

    public function toNotifyTeams($notifiable): NotifyTeams
    {
        return (new NotifyTeams)
            ->channel('equipe')               // opcional: alias do canal
            ->title('Relatório semanal disponível')
            ->message('O relatório de vendas da semana está pronto.')
            ->color('0078D4')
            ->fact('Período', 'Mar/2026')
            ->fact('Total', 'R$ 48.200,00')
            ->action('Ver relatório', 'https://app.com/reports')
            ->tag('relatorios');               // opcional, agrupa/filtra
    }
}
```

**Destino** (precedência: `teamsWebhookUrl` › `channel` alias › webhook padrão da config):

| Método | Descrição |
|---|---|
| `->channel(string)` | Alias do canal (ex.: `equipe`); o servidor resolve para a webhook URL |
| `->teamsWebhookUrl(string)` | Webhook URL direta de destino (override) |

**Card:**

| Método | Descrição |
|---|---|
| `->message(string)` | Corpo da mensagem. Máx: 4000 chars (obrigatório, salvo com `card` cru) |
| `->title(string)` | Título do card. Máx: 255 |
| `->subtitle(string)` | Subtítulo do card. Máx: 255 |
| `->color(string)` | Cor hex sem `#`, ex: `0078D4` (o `#` é removido automaticamente) |
| `->imageUrl(string)` | Imagem do card |
| `->fact(label, value)` | Par chave-valor no card |
| `->facts(array)` | Define todos os facts de uma vez (substitui) |
| `->action(label, url)` | Botão de ação |
| `->actions(array)` | Define todos os botões de uma vez (substitui) |
| `->card(array)` | MessageCard/Adaptive Card cru (passthrough; ignora o atalho acima) |

**Servidor:**

| Método | Descrição |
|---|---|
| `->tag(string\|array)` | Tag(s) para agrupar/filtrar. Acumula e mescla com `notify.teams.tags` |
| `->configId(string)` | Credencial Teams (UUID ou label). Sobrescreve `notify.teams.config_id` |
| `->webhookUrl(string)` | URL de callback **de status** (default: `notify.webhook` global) |

> ⚠️ Não confunda `->teamsWebhookUrl()` (destino de entrega no Teams) com `->webhookUrl()`
> (callback de status que o **seu** app recebe).

**Defaults no `config/notify.php`:**

```php
'teams' => [
    'config_id' => env('NOTIFY_TEAMS_CONFIG_ID'), // usado quando não passa ->configId()
    'tags'      => [],                            // mescladas com as da notificação
],
```

Precedência: `->configId()` da notificação **sobrescreve** o `config_id` do config; as tags
da notificação são **somadas** às `notify.teams.tags` (sem duplicar).

**Exemplos:**

```php
use RiseTechApps\Notify\Message\NotifyTeams;

// Texto simples
(new NotifyTeams)->message('Build #482 finalizado ✅')->tag('deploys');

// Por alias de canal
(new NotifyTeams)->channel('equipe')->message('Deploy de produção concluído.');

// Card completo (com subtítulo, imagem, facts e ações)
(new NotifyTeams)
    ->title('Novo incidente')
    ->subtitle('payments-api')
    ->message('Falha no serviço de pagamento.')
    ->color('E81123')   // vermelho
    ->imageUrl('https://cdn.app.com/alert.png')
    ->fact('Severidade', 'Alta')
    ->fact('Serviço', 'payments-api')
    ->action('Abrir runbook', 'https://app.com/runbooks/payments')
    ->action('Ver dashboard', 'https://app.com/status');

// Card cru (MessageCard/Adaptive Card) — passthrough total
(new NotifyTeams)->card([
    '@type'    => 'MessageCard',
    '@context' => 'http://schema.org/extensions',
    'summary'  => 'Resumo',
    'sections' => [['activityTitle' => 'Meu card', 'text' => 'Conteúdo']],
]);

// Webhook de destino direto + callback de status próprio
(new NotifyTeams)
    ->teamsWebhookUrl('https://outlook.office.com/webhook/X')
    ->message('Aviso')
    ->webhookUrl('https://sua-app.com/notify/webhook');
```

**Envio on-demand** (sem model):

```php
use Illuminate\Support\Facades\Notification;

Notification::route('teams', $idOuWebhook)->notify(new RelatorioSemanal());
```

**Ciclo de vida completo do Teams:**

| Etapa | Como |
|---|---|
| Enviar | `$user->notify(...)` ou `Notification::route('teams', $alvo)->notify(...)` |
| Acompanhar status | eventos `NotifySentEvent` e `NotifyWebhookEvent` — ver [Eventos](#eventos-laravel) |
| Listar histórico | `NotifyQuery::server()->teams()->tag('relatorios')->status('sent')->get()` |
| Detalhe + timeline | `NotifyQuery::server()->teams('uuid')->get()` |
| Cancelar (se `created`) | `NotifyQuery::server()->teams('uuid')->cancel()` |

Detalhes em [Consultas de status → Teams](#teams-endpoints-dedicados).

> O Teams entrega via Incoming Webhook — **não há** editar/apagar mensagem como em
> Telegram/Slack/Discord.

---

### WebSocket

Dispara um evento em tempo real em um canal público, privado ou de presença.
O servidor enfileira (resposta `202` com `notification_id`) e devolve o status pelo webhook.

```php
use RiseTechApps\Notify\Message\NotifyWebSocket;

class PedidoAtualizado extends Notification
{
    public function via($notifiable): array
    {
        return ['notify.websocket'];
    }

    public function toNotifyWebSocket($notifiable): NotifyWebSocket
    {
        return (new NotifyWebSocket)
            ->channel("private-user.{$notifiable->id}")
            ->event('OrderStatusUpdated')
            ->data(['order_id' => 1234, 'status' => 'shipped'])
            ->private()
            ->tag('pedidos');               // opcional, agrupa/filtra
    }
}
```

| Método | Descrição |
|---|---|
| `->channel(string)` | Canal, ex: `notifications`, `private-user.123` |
| `->event(string)` | Nome do evento, ex: `OrderUpdated` |
| `->data(array)` | Payload do evento |
| `->private(bool?)` | Canal privado (prefixo `private-` aplicado pelo servidor; requer auth) |
| `->presence(bool?)` | Canal de presence (prefixo `presence-` aplicado pelo servidor) |
| `->tag(string\|array)` | Tag(s) para agrupar/filtrar. Acumula e mescla com `notify.websocket.tags` |
| `->configId(string)` | Credencial (UUID ou label). Sobrescreve `notify.websocket.config_id` |
| `->webhookUrl(string)` | URL de callback **de status** (default: `notify.webhook` global) |

**Defaults no `config/notify.php`:**

```php
'websocket' => [
    'config_id' => env('NOTIFY_WEBSOCKET_CONFIG_ID'), // usado quando não passa ->configId()
    'tags'      => [],                               // mescladas com as da notificação
],
```

Precedência: `->configId()` da notificação **sobrescreve** o `config_id` do config; as tags
da notificação são **somadas** às `notify.websocket.tags` (sem duplicar).

**Exemplos:**

```php
use RiseTechApps\Notify\Message\NotifyWebSocket;

// Canal público
(new NotifyWebSocket)
    ->channel('notifications')
    ->event('OrderUpdated')
    ->data(['order_id' => '1234'])
    ->tag('pedidos');

// Canal privado (por usuário)
(new NotifyWebSocket)
    ->channel("user.{$userId}")
    ->event('NewMessage')
    ->data(['from' => 'Ana', 'preview' => 'Bom dia!'])
    ->private();

// Canal de presença
(new NotifyWebSocket)
    ->channel('sala-suporte')
    ->event('UserJoined')
    ->data(['user' => 'João'])
    ->presence();

// Credencial específica + múltiplas tags
(new NotifyWebSocket)
    ->channel('notifications')
    ->event('Broadcast')
    ->data(['msg' => 'Manutenção às 22h'])
    ->configId('Pusher Produção')
    ->tag(['avisos', 'sistema']);
```

**Envio on-demand** (sem model):

```php
use Illuminate\Support\Facades\Notification;

Notification::route('websocket', 'notifications')->notify(new PedidoAtualizado());
```

**Ciclo de vida completo do WebSocket:**

| Etapa | Como |
|---|---|
| Enviar | `$user->notify(...)` ou `Notification::route('websocket', $canal)->notify(...)` |
| Acompanhar status | eventos `NotifySentEvent` e `NotifyWebhookEvent` — ver [Eventos](#eventos-laravel) |
| Listar histórico | `NotifyQuery::server()->websocket()->tag('pedidos')->status('sent')->get()` |
| Detalhe + timeline | `NotifyQuery::server()->websocket('uuid')->get()` |
| Cancelar (se `created`) | `NotifyQuery::server()->websocket('uuid')->cancel()` |

Detalhes em [Consultas de status → WebSocket](#websocket-endpoints-dedicados).

---

### Webhook

Faz uma requisição HTTP a um endpoint de destino (ERP, sistema externo etc.). O servidor
dispara a chamada e devolve o status pelo callback. Não confunda o `url` (destino da
requisição) com o `webhookUrl` (seu callback de status).

```php
use RiseTechApps\Notify\Message\NotifyWebhook;

class PedidoCriado extends Notification
{
    public function via($notifiable): array
    {
        return ['notify.webhook'];
    }

    public function toNotifyWebhook($notifiable): NotifyWebhook
    {
        return (new NotifyWebhook)
            ->url('https://erp.empresa.com/api/eventos')
            ->method('POST')
            ->payload(['evento' => 'pedido_criado', 'id' => 1234])
            ->bearerAuth('token-secreto')
            ->timeout(15)
            ->tag('erp');               // opcional, agrupa/filtra
    }
}
```

| Método | Descrição |
|---|---|
| `->url(string)` | URL de destino. Máx: 2048 chars (se omitido, usa o `default_url` da config) |
| `->method(string)` | `POST` \| `GET` \| `PUT` \| `PATCH`. Default: `POST` |
| `->payload(array)` | Body da requisição |
| `->header(key, value)` | Header customizado |
| `->headers(array)` | Múltiplos headers de uma vez |
| `->bearerAuth(token)` | Autenticação Bearer |
| `->basicAuth(user, pass)` | Autenticação Basic |
| `->apiKeyAuth(token)` | Autenticação por API Key |
| `->hmacAuth()` | Assinatura HMAC |
| `->timeout(int)` | Timeout em segundos. Min: 1, Máx: 60 |
| `->tag(string\|array)` | Tag(s) para agrupar/filtrar no histórico (acumula) |
| `->configId(string)` | Credencial Webhook (UUID ou label) |
| `->webhookUrl(string)` | URL de callback **de status** (default: `notify.webhook` global) |

> O canal webhook **não tem** bloco de defaults em `config/notify.php` (a chave
> `notify.webhook` já é o callback global de status). Defina `tag`/`configId` por mensagem.

**Notas de autenticação e entrega:**
- `bearerAuth`/`apiKeyAuth` exigem o token; `basicAuth` exige usuário+senha. Para API Key, o
  header é o `api_key_header` da config (default `X-API-KEY`).
- `hmacAuth()` assina o payload com o `secret` **da config** (SHA-256) e envia
  `X-Notify-Signature` + `X-Hub-Signature-256` — o segredo não vai no request.
- Se a config tiver `inject_metadata: true` (default), o servidor injeta um bloco
  `_notify` (`timestamp`, `source`, `channel`) no payload entregue ao destino.
- Sucesso = destino respondeu `2xx`. `4xx` (exceto 408/429) = falha permanente (sem retry);
  `5xx`/timeout/conexão/408/429 = retry com backoff + failover (se houver mais endpoints).

**Envio on-demand** (sem model):

```php
use Illuminate\Support\Facades\Notification;

Notification::route('webhook', 'https://erp.empresa.com/api/eventos')->notify(new PedidoCriado());
```

**Ciclo de vida completo do Webhook:**

| Etapa | Como |
|---|---|
| Enviar | `$user->notify(...)` ou `Notification::route('webhook', $url)->notify(...)` |
| Acompanhar status | eventos `NotifySentEvent` e `NotifyWebhookEvent` — ver [Eventos](#eventos-laravel) |
| Listar histórico | `NotifyQuery::server()->webhook()->tag('erp')->status('sent')->get()` |
| Detalhe + timeline | `NotifyQuery::server()->webhook('uuid')->get()` |
| Cancelar (se `created`) | `NotifyQuery::server()->webhook('uuid')->cancel()` |

Detalhes em [Consultas de status → Webhook](#webhook-endpoints-dedicados).

**Config do driver `generic`** (credenciais/defaults no servidor):

```php
NotifyQuery::server()->webhook()->config()
    ->driver('generic')
    ->label('ERP Empresa')
    ->defaultUrl('https://erp.empresa.com/api/eventos')
    ->authType('bearer')
    ->authToken('token-secreto')
    ->timeout(15)
    ->injectMetadata(true)
    ->asDefault()
    ->save();
```

---

## Envio multicanal

Dispara vários canais em **uma única requisição** (`POST /api/v1/send/multi`), sem passar
pelo sistema de notificações do Laravel. Útil para alertas que precisam sair por SMS,
e-mail e push ao mesmo tempo.

```php
use RiseTechApps\Notify\NotifyQuery;

$result = NotifyQuery::multi([
    ['channel' => 'sms',   'data' => ['to' => '5521999887766', 'content' => 'Servidor fora do ar']],
    ['channel' => 'email', 'data' => ['email' => 'ops@app.com', 'name' => 'Ops', 'subject' => 'Alerta']],
    ['channel' => 'telegram', 'data' => ['chat_id' => '-1001234567890', 'message' => 'Alerta']],
], webhookUrl: 'https://sua-app.com/notify/webhook')->send();
```

Cada item tem `channel` (`sms`, `email`, `push`, `apns`, `telegram`, `slack`, `discord`,
`teams`, `websocket`, `webhook`) e `data` com o payload daquele canal. Para não montar
`data` na mão, reaproveite a classe de mensagem:

```php
use RiseTechApps\Notify\Message\NotifySms;

$sms = (new NotifySms)->to('5521999887766')->content('Alerta')->tag('ops');

NotifyQuery::multi([
    ['channel' => 'sms', 'data' => $sms->toArray()],
])->send();
```

O segundo argumento (`webhookUrl`) é o callback global do disparo; se omitido, cada canal
segue o `webhook_url` que estiver no seu próprio `data` ou o padrão do servidor.
O retorno é o array cru da resposta — ou `['error' => ...]` em falha, igual aos canais.

> `NotifyQuery::multi()` **não** dispara os eventos `NotifySendingEvent`/`NotifySentEvent`
> (esses são do fluxo de notificação do Laravel). Acompanhe o resultado pelo retorno e
> pelos callbacks de webhook.

---

## Credenciais por driver

> Para criar/atualizar/remover configurações, use `NotifyQuery::server()->{canal}()->config()`
> — veja [Configurações de driver](#configurações-de-driver).


> Fonte de verdade: `NotifyQuery::server()->drivers()` (lido dinamicamente do servidor).
> A tabela abaixo reflete o contrato atual; o `default_driver` de cada canal está marcado.

| Canal | Driver | Credenciais (`credential_fields`) |
|---|---|---|
| `sms` | `mobizon` *(padrão)* | `key`, `api_server` |
| `sms` | `clicksend` | `username`, `api_key`, `from` |
| `sms` | `twilio` | `sid`, `token`, `from` |
| `email` | `smtp` *(padrão)* | *(nenhuma — usa `config/mail.php`)* |
| `email` | `ses` | `key`, `secret`, `region` |
| `email` | `mailgun` | `domain`, `secret`, `endpoint` |
| `email` | `sendgrid` | `api_key` |
| `email` | `postmark` | `token` |
| `email` | `resend` | `api_key` |
| `push` | `fcm` | `credentials_json` (objeto do Service Account) — `->credentialsFile()` lê o `.json` e embeda; ou `->credentialsJson()` inline |
| `apns` | `apns` | `key_id`, `team_id`, `bundle_id`, `key_path`, `production` |
| `telegram` | `telegram` | `bot_token` |
| `slack` | `slack` | `bot_token`, `webhook_url`, `default_channel` |
| `discord` | `webhook` | `webhook_url`, `username`, `avatar_url` |
| `teams` | `webhook` | `webhook_url`, `theme_color` |
| `websocket` | `pusher` | `app_id`, `key`, `secret`, `cluster`, `host`, `port`, `scheme` |
| `webhook` | `generic` | `default_url`, `timeout`, `auth_type`, `auth_token`, `auth_user`, `auth_password`, `api_key_header`, `secret`, `inject_metadata` |

### Credenciais inline

Alternativa ao `config_id`: mandar as credenciais **dentro do próprio disparo**, sem criar
uma config salva no servidor. A classe `NotifyCredentials` monta o array
`['driver' => ..., 'credentials' => [...]]` já com as chaves que o servidor espera.

```php
use RiseTechApps\Notify\NotifyCampaignBuilder;
use RiseTechApps\Notify\NotifyCredentials;

NotifyCampaignBuilder::sms()
    ->name('Promo')
    ->content('Olá {name}!')
    ->contacts([...])
    ->credentials(NotifyCredentials::twilio('ACxxx', 'auth-token', '+15551234567'))
    ->send();

NotifyCampaignBuilder::email()
    ->name('Newsletter')
    ->subject('Novidades')
    ->contacts([...])
    ->credentials(NotifyCredentials::resend('re_xxxxxxxxxxxx'))
    ->send();
```

Fábricas disponíveis:

| Canal | Chamada |
|---|---|
| SMS | `NotifyCredentials::mobizon($key, $apiServer = 'api.mobizon.com.br')` · `::twilio($sid, $token, $from)` |
| Email | `::smtp($host, $user, $pass, $port = 587, $encryption = 'tls')` · `::mailgun($domain, $secret, $endpoint)` · `::resend($apiKey)` · `::sendgrid($apiKey)` · `::ses($key, $secret, $region)` · `::postmark($token)` |
| APNS | `::apns($keyPath, $keyId, $teamId, $bundleId, $production = false)` |
| Telegram | `::telegram($botToken)` |
| Slack | `::slack($botToken, $webhookUrl = null, $defaultChannel = '#general')` |
| Discord | `::discord($webhookUrl, $username = null, $avatarUrl = null)` |
| Teams | `::teams($webhookUrl, $themeColor = '0076D7')` |
| WebSocket | `::pusher($appId, $key, $secret, $cluster, $host, $port, $scheme)` |

> ⚠️ `->credentials()` existe **apenas** no `NotifyCampaignBuilder` (e no builder de
> [configurações de driver](#configurações-de-driver)). As classes de mensagem individuais
> (`NotifySms`, `NotifyMail`, …) **não** aceitam credenciais inline — nelas use
> `->configId()`.

> ⚠️ Duas fábricas estão fora do contrato atual do servidor e não devem ser usadas:
> `NotifyCredentials::zenvia()` (driver removido) e `NotifyCredentials::fcm()` (envia
> `project_id` + `credentials_file`, mas o servidor hoje espera `credentials_json` com o
> objeto do Service Account). Para push, crie a config com
> `->config()->driver('fcm')->credentialsFile(...)` — veja [Criar — Push / APNS](#criar--push--apns).

### Usando uma config específica no envio

Todos os canais e o `NotifyCampaignBuilder` aceitam `->configId(string)` para sobrescrever a config padrão:

```php
// Notificação individual
public function toNotifySms($notifiable): NotifySms
{
    return (new NotifySms)
        ->to($notifiable->phone)
        ->content('Mensagem')
        ->configId('uuid-da-config-twilio-backup');
}

// Campanha
NotifyCampaignBuilder::sms()
    ->name('Promo')
    ->content('Olá {name}!')
    ->contacts([...])
    ->configId('uuid-da-config')
    ->send();
```

---

## Campanhas em massa

Campanhas não usam o sistema de notificações do Laravel — são disparadas diretamente pela classe `NotifyCampaignBuilder`. Suportam até **10.000 contatos** por disparo com rate limiting configurável.

---

### Campanha SMS

```php
use RiseTechApps\Notify\NotifyCampaignBuilder;

$campaign = NotifyCampaignBuilder::sms()
    ->name('Promo Dia das Mães')
    ->content('Olá {name}! 30% OFF hoje. Use: {cupom}. Loja.com')
    ->tag(['promo', 'maes'])
    ->contacts([
        ['phone' => '5521981425950', 'name' => 'João', 'extra_data' => ['cupom' => 'MAES30']],
        ['phone' => '5521969014860', 'name' => 'Maria', 'extra_data' => ['cupom' => 'MAES30']],
    ])
    ->webhookUrl('https://sua-app.com/notify/webhook/campaign')
    ->ratePerMinute(100)
    ->send();

// $campaign é o array cru da resposta do servidor (campaign_id, status, ...)
```

| Método | Descrição |
|---|---|
| `->name(string)` | Nome da campanha |
| `->content(string)` | Texto do SMS. Máx: 160 chars. Variáveis (chave simples): `{name}`, `{phone}` + chaves de `extra_data` |
| `->tag(string\|array)` | Tag(s) propagada(s) para cada notificação |
| `->contacts(array)` | Array direto: `[['phone' => '...', 'name' => '...', 'extra_data' => [...]]]` |
| `->fromQuery(Builder, col, nameCol?, extraCols?)` | Via query Eloquent — processada em chunks de 500 |
| `->fromCollection(Collection, col, nameCol?, extraCols?)` | Via Collection Laravel |
| `->configId(string)` | UUID da config SMS |
| `->webhookUrl(string)` | URL de callback de progresso |
| `->ratePerMinute(int)` | Envios por minuto. Min: 1, Máx: 600. Default: 60 |
| `->scheduledAt(string)` | Agendamento: `'2026-06-01 08:00:00'` |
| `->credentials(array)` | [Credenciais inline](#credenciais-inline) via `NotifyCredentials` |
| `->send()` | Envia e retorna o array da resposta do servidor |

> O servidor não aceita mais `from`/sender ID em SMS — o remetente vem da credencial (config).

> `->scheduledAt()` é convertido para ISO 8601 **com offset** usando `config('app.timezone')`
> antes de subir (`'2026-06-01 08:00:00'` → `2026-06-01T08:00:00-03:00`). Passe o horário no
> fuso da sua aplicação; o servidor recebe o offset explícito e não precisa adivinhar.

---

### Campanha Email

```php
use RiseTechApps\Notify\NotifyCampaignBuilder;

$campaign = NotifyCampaignBuilder::email()
    ->name('Newsletter Março 2026')
    ->subject('Novidades de Março!')
    ->line('Confira as novidades deste mês.')
    ->action('https://app.com/blog', 'Ler mais')
    ->from('news@app.com', 'Minha App')
    ->tag('newsletter')
    ->contacts([
        ['email' => 'joao@email.com', 'name' => 'João'],
        ['email' => 'maria@email.com', 'name' => 'Maria'],
    ])
    ->webhookUrl('https://sua-app.com/notify/webhook/campaign')
    ->scheduledAt('2026-03-15 09:00:00')
    ->send();
```

> Email usa placeholder de chave dupla: `{{name}}` + chaves de `extra_data` (ex.: `{{cupom}}`).
> Se `->from()` não for informado, cai para `config('notify.mail.from.address'|'name')`.

| Método | Descrição |
|---|---|
| `->name(string)` | Nome da campanha |
| `->subject(string)` | Assunto do email |
| `->subjectMessage(string)` | Subtítulo / preview |
| `->line(string)` | Linha de texto principal |
| `->lineHeader(string)` | Linha no cabeçalho (chamadas múltiplas) |
| `->lineFooter(string)` | Linha no rodapé |
| `->action(url, text)` | Botão de call-to-action |
| `->theme(string)` | Tema do template. Default: `default` |
| `->signature(string)` | Assinatura |
| `->from(email, name?)` | Remetente (default: `config('notify.mail.from.*')`) |
| `->tag(string\|array)` | Tag(s) propagada(s) para cada notificação |
| `->addTable(EmailTable\|array)` | Tabela no corpo: `['headers' => [], 'rows' => [[]]]` |
| `->addList(type, items)` | Lista `ordered` ou `unordered` |
| `->contacts(array)` | Array direto: `[['email' => '...', 'name' => '...', 'extra_data' => [...]]]` |
| `->fromQuery(Builder, col, nameCol?, extraCols?)` | Via query Eloquent |
| `->fromCollection(Collection, col, nameCol?, extraCols?)` | Via Collection |
| `->configId(string)` | UUID da config de email |
| `->webhookUrl(string)` | URL de callback |
| `->ratePerMinute(int)` | Envios por minuto. Default: 60 |
| `->scheduledAt(string)` | Agendamento futuro (ISO 8601 com o offset de `config('app.timezone')`) |
| `->credentials(array)` | [Credenciais inline](#credenciais-inline) via `NotifyCredentials` |
| `->send()` | Envia com o template do pacote e retorna o array da resposta |
| `->sendHtml(string $path)` | Envia usando um [HTML próprio](#template-html-próprio) |

---

### Template HTML próprio

Em vez do template do pacote, você pode enviar o **seu** HTML: use `sendHtml()` com o
caminho do arquivo. O conteúdo é lido e sobe no campo `html_raw`.

```php
NotifyCampaignBuilder::email()
    ->name('Black Friday')
    ->subject('50% OFF só hoje')
    ->tag('bf')
    ->contacts([
        ['email' => 'joao@email.com', 'name' => 'João', 'extra_data' => ['cupom' => 'BF50']],
    ])
    ->sendHtml(resource_path('mail/black-friday.html'));
```

Os placeholders continuam valendo dentro do HTML — `{{name}}`, `{{email}}` e as chaves de
`extra_data`. Métodos de montagem do template do pacote (`->line()`, `->action()`,
`->addTable()`, `->theme()`, …) são ignorados nesse modo; `subject`, `subject_message` e
`app_name` continuam sendo enviados.

Se o arquivo não existir ou não for legível, `sendHtml()` lança
`\InvalidArgumentException` — nada é enviado.

---

### Validações antes do disparo

`send()` e `sendHtml()` validam localmente e lançam `\InvalidArgumentException` **antes**
de qualquer chamada HTTP:

| Situação | Mensagem |
|---|---|
| Sem `->name()` | `Campaign name is required.` |
| Campanha SMS sem `->content()` | `SMS content is required.` |
| Campanha Email sem `->subject()` | `Email subject is required.` |
| Nenhum contato resolvido | `At least one contact is required.` |
| `sendHtml()` com caminho inválido | `HTML template file not found or unreadable: {path}` |

Erros vindos do servidor **não** viram exceção: o retorno é o corpo de erro da API, ou
`['error' => 'mensagem']` em falha de transporte.

---

### Fontes de contatos

**Array direto:**
```php
->contacts([
    ['phone' => '5511999887766', 'name' => 'João'],                          // SMS
    ['email' => 'joao@email.com', 'name' => 'João', 'extra_data' => [...]], // Email
])
```

**Via query Eloquent** — não carrega todos os registros em memória, processa em chunks de 500:
```php
->fromQuery(
    User::where('active', true)->where('accepts_sms', true),
    contactColumn: 'phone',              // coluna com telefone ou email no banco
    nameColumn: 'name',                  // opcional
    extraColumns: ['cupom' => 'coupon']  // viram extra_data (placeholders); ['cupom'] mantém o nome
)
```

**Via Collection:**
```php
->fromCollection($users, contactColumn: 'email', nameColumn: 'full_name', extraColumns: ['cidade'])
```

> `extraColumns` aceita lista (`['cidade']` → placeholder `{cidade}`/`{{cidade}}` com o mesmo nome)
> ou mapa (`['cupom' => 'coupon_code']` → placeholder `{cupom}` lendo a coluna `coupon_code`).

---

## Estado e rastreamento

O pacote **não persiste nada localmente** — não há tabelas, models nem migrations.
Todo o estado (status, eventos, contadores de campanha) vive no servidor.

Para acompanhar uma notificação você tem dois caminhos:

- **Tempo real / sob demanda** → consulte o servidor com `NotifyQuery::server()` (ver abaixo).
- **Push do servidor** → receba os callbacks no [webhook](#webhook-receiver), que são
  repassados como **eventos Laravel** (`NotifyWebhookEvent` / `NotifyCampaignWebhookEvent`).
  Persista o que quiser no seu próprio banco a partir do listener.

---

## Webhook receiver

O package inclui `NotifyWebhookController` que recebe os callbacks do servidor e os
**repassa como eventos Laravel** — ele não grava nada. Escute os eventos para reagir.

### Validação de assinatura

O servidor assina cada callback com HMAC-SHA256 e envia o resultado no header:

```
X-Notify-Signature: t=1750000000,v1=9f86d081884c7d65...
```

O material assinado é `{timestamp}.{corpo bruto}`. O middleware `VerifyNotifySignature`
valida isso automaticamente antes de o controller rodar:

1. recalcula o HMAC sobre `{t}.{corpo bruto}` usando `notify.webhook_secret`;
2. compara com `hash_equals()` (comparação em tempo constante);
3. recusa payloads cujo `t` esteja fora da janela de `notify.webhook_tolerance`
   segundos (anti-replay, default 300).

Qualquer falha retorna `403` e o controller não é executado.

| Config | Default | Descrição |
|---|---|---|
| `notify.webhook_secret` | `''` | Segredo compartilhado, obtido no painel do NotifyKit. **Sem ele todos os callbacks são recusados com 403.** |
| `notify.webhook_verify` | `true` | Liga/desliga a validação. Só desligue em ambiente local. |
| `notify.webhook_tolerance` | `300` | Janela anti-replay em segundos. `0` desliga a checagem de tempo. |

O header aceita mais de um `v1` (`t=...,v1=abc...,v1=def...`) — útil durante rotação
de segredo: basta um deles conferir.

> ⚠️ A validação usa o **corpo bruto** (`$request->getContent()`). Se você escrever a
> sua própria verificação, nunca use `json_encode($request->all())`: a ordem das chaves
> e o escape de `/` e de unicode mudam os bytes e a assinatura nunca vai conferir.

**Notificação individual** → dispara `NotifyWebhookEvent`. Payload típico recebido:
```json
{
    "event": "sent",
    "notification_id": "uuid-do-servidor",
    "provider_id": "11",
    "status": "send",
    "type": "telegram"
}
```

**Campanha** → dispara `NotifyCampaignWebhookEvent`. Payload típico:
```json
{
    "campaign_id": "uuid-da-campanha-no-servidor",
    "status": "processing",
    "total": 1000,
    "sent": 450,
    "failed": 12,
    "contact_updates": [
        { "contact": "joao@email.com", "status": "sent" },
        { "contact": "erro@email.com", "status": "failed", "error": "Mailbox full" }
    ]
}
```

Exemplo de listener:
```php
use RiseTechApps\Notify\Events\NotifyWebhookEvent;

public function handle(NotifyWebhookEvent $event): void
{
    // $event->event, $event->notificationId, $event->providerId,
    // $event->status, $event->type, $event->payload (array cru completo)

    match ($event->status) {
        'delivered' => /* ... */ null,
        'error'     => /* ... */ null,
        default     => null,
    };
}
```

---

## Consultas de status

Todas as consultas vão ao servidor (HTTP com a `NOTIFY_SERVICE_KEY`), via `NotifyQuery::server()`.

O helper global `notifyQuery()` é um atalho equivalente — use o que preferir:

```php
NotifyQuery::server()->sms()->get();
notifyQuery()->sms()->get();          // idêntico
```

**Filtros comuns a todos os canais:** `->tag(string|array)`, `->untagged(bool)`,
`->status(string)`, `->perPage(int)` (1–100, default 20) e `->page(int)`.
`->untagged()` pede explicitamente os registros **sem** tag.

### SMS (endpoints dedicados)

```php
use RiseTechApps\Notify\NotifyQuery;

// Listar (filtra por tag/status) — retorna o paginador do servidor
$result = NotifyQuery::server()->sms()
    ->tag('promo')                 // ou ->tag(['promo', 'junho']) — contém qualquer uma
    ->status('delivered')          // created|sending|send|delivered|error|cancelled
    ->perPage(50)                  // 1–100, default 20
    ->page(1)
    ->get();

$result['data'];          // array de SMS
$result['total'];         // total, current_page, last_page, per_page

// Detalhe + timeline de eventos
$sms = NotifyQuery::server()->sms('server-uuid')->get();
$sms['status'];           // status atual
$sms['events'];           // timeline: created → sending → send → delivered

// Cancelar — só funciona enquanto o status for `created` (ainda na fila)
$res = NotifyQuery::server()->sms('server-uuid')->cancel();
// ->sms()->cancel('server-uuid') também funciona
// ['status' => bool, 'message' => string|null, 'current_status' => string|null, 'http' => int]
// http 200 = cancelado · 409 = já enviando/enviado (veja current_status) · 404 = não é seu/inexistente
```

> Atenção ao filtro de tag: conforme o contrato do servidor, **sem informar `tag` a
> listagem retorna apenas os SMS SEM tag**. Informe `->tag(...)` para trazer os que
> contêm a(s) tag(s) desejada(s).

### Email (endpoints dedicados)

```php
use RiseTechApps\Notify\NotifyQuery;

// Listar (filtra por tag/status) — retorna o paginador do servidor
$result = NotifyQuery::server()->mail()
    ->tag('pedido')                // ou ->tag(['pedido', 'transacional']) — contém qualquer uma
    ->status('delivered')          // created|sending|sent|delivered|ready|error|cancelled
    ->perPage(50)                  // 1–100, default 20
    ->page(1)
    ->get();

// Detalhe + timeline de eventos
$mail = NotifyQuery::server()->mail('server-uuid')->get();
$mail['events'];          // timeline: created → sending → sent → delivered/ready/error

// Cancelar — só funciona enquanto o status for `created` (ainda na fila)
$res = NotifyQuery::server()->mail('server-uuid')->cancel();
// ->mail()->cancel('server-uuid') também funciona
// ['status' => bool, 'message' => string|null, 'notification_id' => string|null, 'current_status' => string|null, 'http' => int]
// http 200 = cancelado · 409 = já enviando/finalizado (veja current_status) · 404 = não é seu/inexistente
```

> Mesmo comportamento do SMS no filtro de tag: **sem `tag()` a listagem traz só os
> e-mails SEM tag**. Informe `->tag(...)` para trazer os que contêm a(s) tag(s).

**Preview do e-mail enviado** — recupera o HTML já renderizado pelo servidor (com os
placeholders resolvidos), útil para exibir "ver no navegador" ou depurar template:

```php
// HTML cru (string) — o padrão
$html = NotifyQuery::server()->mail('server-uuid')->preview();

// Metadados + html em JSON
$data = NotifyQuery::server()->mail('server-uuid')->preview('json');

// Link temporário hospedado no servidor
$link = NotifyQuery::server()->mail('server-uuid')->previewLink();
// array cru da resposta do servidor, com a URL do preview
```

`preview()` devolve `string` no modo HTML e `array` no modo `json`. Em falha (ou sem id
informado) os dois métodos devolvem `['error' => 'mensagem']` — cheque antes de renderizar.

### Push (endpoints dedicados)

```php
use RiseTechApps\Notify\NotifyQuery;

// Listar (filtra por tag/status) — retorna o paginador do servidor
$result = NotifyQuery::server()->push()
    ->tag('promo')                 // string única (no push a tag não é array)
    ->status('sent')               // created|sending|sent|error|failed|cancelled
    ->perPage(50)                  // 1–100, default 20
    ->page(1)
    ->get();

// Detalhe + timeline de eventos
$push = NotifyQuery::server()->push('server-uuid')->get();
$push['events'];          // timeline: created → sending → sent · error · failed

// Cancelar — só funciona enquanto o status for `created` (ainda na fila)
$res = NotifyQuery::server()->push('server-uuid')->cancel();
// ->push()->cancel('server-uuid') também funciona
// ['status' => bool, 'message' => string|null, 'current_status' => string|null, 'http' => int]
// http 200 = cancelado · 409 = já enviando/enviado (veja current_status) · 404 = não é seu/inexistente
```

> Mesmo comportamento de tag dos outros canais: **sem `tag()` a listagem traz só os
> push SEM tag**.

### APNS (endpoints dedicados)

```php
use RiseTechApps\Notify\NotifyQuery;

// Listar (filtra por tag/status) — retorna o paginador do servidor
$result = NotifyQuery::server()->apns()
    ->tag('entregas')              // ou ->tag(['entregas', 'junho'])
    ->status('sent')               // created|sending|sent|error|failed|cancelled
    ->perPage(50)                  // 1–100, default 20
    ->page(1)
    ->get();

// Detalhe + timeline de eventos
$apns = NotifyQuery::server()->apns('server-uuid')->get();
$apns['events'];          // timeline: created → sending → sent · error · failed

// Cancelar — só funciona enquanto o status for `created` (ainda na fila)
$res = NotifyQuery::server()->apns('server-uuid')->cancel();
// ->apns()->cancel('server-uuid') também funciona
// ['status' => bool, 'message' => string|null, 'current_status' => string|null, 'http' => int]
// http 200 = cancelado · 409 = já enviando/enviado (veja current_status) · 404 = não é seu/inexistente
```

> Mesmo comportamento de tag dos outros canais: **sem `tag()` a listagem traz só os
> envios SEM tag**.

### Telegram (endpoints dedicados)

```php
use RiseTechApps\Notify\NotifyQuery;

// Listar (filtra por tag/status) — retorna o paginador do servidor
$result = NotifyQuery::server()->telegram()
    ->tag('pedidos')               // ou ->tag(['pedidos', 'junho'])
    ->status('sent')               // created|sending|sent|error|failed|cancelled
    ->perPage(50)                  // 1–100, default 20
    ->page(1)
    ->get();

// Detalhe + timeline de eventos
$msg = NotifyQuery::server()->telegram('server-uuid')->get();
$msg['events'];           // timeline: created → sending → sent · error · failed

// Cancelar — só funciona enquanto o status for `created` (ainda na fila)
$res = NotifyQuery::server()->telegram('server-uuid')->cancel();
// ->telegram()->cancel('server-uuid') também funciona
// ['status' => bool, 'message' => string|null, 'current_status' => string|null, 'http' => int]
// http 200 = cancelado · 409 = já enviando/enviado (veja current_status) · 404 = não é seu/inexistente

// Editar / apagar / fixar uma mensagem já enviada (síncrono, só o notification_id)
NotifyQuery::server()->telegram('server-uuid')->edit('Texto editado');
NotifyQuery::server()->telegram('server-uuid')->editCaption('Nova legenda');
NotifyQuery::server()->telegram('server-uuid')->delete();
NotifyQuery::server()->telegram('server-uuid')->pin(disableNotification: true);
// ['status' => bool, 'message' => string|null, 'http' => int]
```

> Mais detalhes em [Telegram → Gerenciar mensagens enviadas](#gerenciar-mensagens-enviadas).

> Mesmo comportamento de tag dos outros canais: **sem `tag()` a listagem traz só os
> envios SEM tag**.

### Slack (endpoints dedicados)

```php
use RiseTechApps\Notify\NotifyQuery;

// Listar (filtra por tag/status) — retorna o paginador do servidor
$result = NotifyQuery::server()->slack()
    ->tag('deploys')               // ou ->tag(['deploys', 'junho'])
    ->status('sent')               // created|sending|sent|error|failed|cancelled
    ->perPage(50)                  // 1–100, default 20
    ->page(1)
    ->get();

// Detalhe + timeline de eventos
$slack = NotifyQuery::server()->slack('server-uuid')->get();
$slack['events'];         // timeline: created → sending → sent · error · failed

// Cancelar — só funciona enquanto o status for `created` (ainda na fila)
$res = NotifyQuery::server()->slack('server-uuid')->cancel();
// ->slack()->cancel('server-uuid') também funciona
// ['status' => bool, 'message' => string|null, 'current_status' => string|null, 'http' => int]
// http 200 = cancelado · 409 = já enviando/enviado (veja current_status) · 404 = não é seu/inexistente

// Editar / apagar uma mensagem já enviada (síncrono, só Bot Token)
NotifyQuery::server()->slack('server-uuid')->edit('Texto editado ✅');
NotifyQuery::server()->slack('server-uuid')->delete();
// ['status' => bool, 'message' => string|null, 'http' => int]
```

> Mesmo comportamento de tag dos outros canais: **sem `tag()` a listagem traz só os
> envios SEM tag**.

### Discord (endpoints dedicados)

```php
use RiseTechApps\Notify\NotifyQuery;

// Listar (filtra por tag/status) — retorna o paginador do servidor
$result = NotifyQuery::server()->discord()
    ->tag('cadastros')             // ou ->tag(['cadastros', 'junho'])
    ->status('sent')               // created|sending|sent|error|failed|cancelled
    ->perPage(50)                  // 1–100, default 20
    ->page(1)
    ->get();

// Detalhe + timeline de eventos
$discord = NotifyQuery::server()->discord('server-uuid')->get();
$discord['events'];       // timeline: created → sending → sent · error · failed

// Cancelar — só funciona enquanto o status for `created` (ainda na fila)
$res = NotifyQuery::server()->discord('server-uuid')->cancel();
// ->discord()->cancel('server-uuid') também funciona
// ['status' => bool, 'message' => string|null, 'current_status' => string|null, 'http' => int]
// http 200 = cancelado · 409 = já enviando/enviado (veja current_status) · 404 = não é seu/inexistente

// Editar / apagar uma mensagem já enviada (síncrono)
NotifyQuery::server()->discord('server-uuid')->edit('Incidente resolvido ✅');
NotifyQuery::server()->discord('server-uuid')->delete();
// ['status' => bool, 'message' => string|null, 'http' => int]
```

> Mesmo comportamento de tag dos outros canais: **sem `tag()` a listagem traz só os
> envios SEM tag**.

### Teams (endpoints dedicados)

```php
use RiseTechApps\Notify\NotifyQuery;

// Listar (filtra por tag/status) — retorna o paginador do servidor
$result = NotifyQuery::server()->teams()
    ->tag('relatorios')            // ou ->tag(['relatorios', 'junho'])
    ->status('sent')               // created|sending|sent|error|failed|cancelled
    ->perPage(50)                  // 1–100, default 20
    ->page(1)
    ->get();

// Detalhe + timeline de eventos
$teams = NotifyQuery::server()->teams('server-uuid')->get();
$teams['events'];         // timeline: created → sending → sent · error · failed

// Cancelar — só funciona enquanto o status for `created` (ainda na fila)
$res = NotifyQuery::server()->teams('server-uuid')->cancel();
// ->teams()->cancel('server-uuid') também funciona
// ['status' => bool, 'message' => string|null, 'current_status' => string|null, 'http' => int]
// http 200 = cancelado · 409 = já enviando/enviado (veja current_status) · 404 = não é seu/inexistente
```

> Mesmo comportamento de tag dos outros canais: **sem `tag()` a listagem traz só os
> envios SEM tag**.

### WebSocket (endpoints dedicados)

```php
use RiseTechApps\Notify\NotifyQuery;

// Listar (filtra por tag/status) — retorna o paginador do servidor
$result = NotifyQuery::server()->websocket()
    ->tag('pedidos')               // ou ->tag(['pedidos', 'junho'])
    ->status('sent')               // created|sending|sent|error|failed|cancelled
    ->perPage(50)                  // 1–100, default 20
    ->page(1)
    ->get();

// Detalhe + timeline de eventos
$ws = NotifyQuery::server()->websocket('server-uuid')->get();
$ws['events'];            // timeline: created → sending → sent · error · failed

// Cancelar — só funciona enquanto o status for `created` (ainda na fila)
$res = NotifyQuery::server()->websocket('server-uuid')->cancel();
// ->websocket()->cancel('server-uuid') também funciona
// ['status' => bool, 'message' => string|null, 'current_status' => string|null, 'http' => int]
// http 200 = cancelado · 409 = já enviando/enviado (veja current_status) · 404 = não é seu/inexistente
```

> Mesmo comportamento de tag dos outros canais: **sem `tag()` a listagem traz só os
> envios SEM tag**.

### Webhook (endpoints dedicados)

```php
use RiseTechApps\Notify\NotifyQuery;

// Listar (filtra por tag/status) — retorna o paginador do servidor
$result = NotifyQuery::server()->webhook()
    ->tag('erp')                   // ou ->tag(['erp', 'junho'])
    ->status('sent')               // created|sending|sent|error|cancelled
    ->perPage(50)                  // 1–100, default 20
    ->page(1)
    ->get();

// Detalhe + timeline de eventos
$wh = NotifyQuery::server()->webhook('server-uuid')->get();
$wh['events'];            // timeline: created → sending → sent (metadata.status_code) · error

// Cancelar — só funciona enquanto o status for `created` (ainda na fila)
$res = NotifyQuery::server()->webhook('server-uuid')->cancel();
// ->webhook()->cancel('server-uuid') também funciona
// ['status' => bool, 'message' => string|null, 'notification_id' => string|null, 'current_status' => string|null, 'http' => int]
// http 200 = cancelado · 409 = já enviando/enviado (veja current_status) · 404 = não é seu/inexistente
```

> Mesmo comportamento de tag dos outros canais: **sem `tag()` a listagem traz só os
> envios SEM tag**.

### Notificações (genérico, todos os canais)

```php
use RiseTechApps\Notify\NotifyQuery;

// ── Notificações ─────────────────────────────────────────────────────────────

// Listar — retorna ['data' => [...], 'meta' => [...]]
$result = NotifyQuery::server()->notifications()
    ->channel('sms')          // sms|email|push|apns|telegram|slack|discord|teams|websocket|webhook
    ->status('send')          // created|sending|send|delivered|ready|error
    ->from('2026-01-01')
    ->to('2026-03-31')
    ->campaignId('uuid')      // filtra por campanha
    ->perPage(50)             // máx: 100, default: 25
    ->page(2)
    ->get();

$result['data']; // array de notificações
$result['meta']; // total, per_page, current_page, last_page

// Detalhes + timeline de eventos de uma notificação
$notification = NotifyQuery::server()->notification('server-uuid');
// Inclui todos os campos + array 'events' com a timeline completa

// Só a timeline (ideal para polling)
$events = NotifyQuery::server()->notificationEvents('server-uuid');
$events['current_status']; // status atual
$events['events'];         // array de eventos com timestamps

// ── Campanhas ─────────────────────────────────────────────────────────────────
// Endpoints por canal: /api/v1/send/campaigns/{sms|mail}. Informe o canal no 1º arg.

// Listar campanhas
$result = NotifyQuery::server()->campaigns('mail')   // 'sms' | 'mail' (email vira mail)
    ->status('completed')           // pending|processing|paused|completed|failed|cancelled
    ->tag('newsletter')             // opcional
    ->from('2026-01-01')
    ->to('2026-03-31')
    ->perPage(25)
    ->get();

// Detalhes + progresso de uma campanha (canal + id)
$campaign = NotifyQuery::server()->campaigns('mail', 'server-campaign-uuid')->get();
$campaign['progress_percent']; // 0 a 100
$campaign['pending_count'];    // contatos ainda não processados

// Cancelar campanha (pending/paused/processing)
$res = NotifyQuery::server()->campaigns('sms', 'server-campaign-uuid')->cancel();
$res['status'];          // true se cancelou
$res['current_status'];  // status atual em caso de 409
$res['http'];            // 200 | 409 | 404
// Forma alternativa: ->campaigns('sms')->cancel('server-campaign-uuid')

// Contatos da campanha — status e motivo da falha por destinatário (exige o id)
$contacts = NotifyQuery::server()->campaigns('mail', 'server-campaign-uuid')->contacts()
    ->status('failed')      // pending|sending|sent|failed|skipped (opcional)
    ->page(1)               // opcional
    ->get();

$contacts['data'];         // [['contact','name','status','error','sent_at'], ...]
$contacts['pagination'];   // ['current_page','per_page','total','last_page']
// Ideal para descobrir QUAIS contatos falharam e o porquê (ex.: 'Domínio sem MX').
```

---

## Eventos Laravel

Como nada é persistido, **os eventos são o principal ponto de integração**. O pacote
dispara dois grupos:

**No envio** (dentro do canal, a cada notificação individual):

```php
use RiseTechApps\Notify\Events\NotifySendingEvent;  // antes do POST ao servidor
use RiseTechApps\Notify\Events\NotifySentEvent;     // ($notifiable, $notification, $response, $channel)
use RiseTechApps\Notify\Events\NotifyFailedEvent;   // ($notifiable, $notification, $exception, $channel)
```

**No webhook** (callback do servidor, sem `$notifiable`/`$notification`):

```php
use RiseTechApps\Notify\Events\NotifyWebhookEvent;
// (event, notificationId, providerId, status, type, payload)

use RiseTechApps\Notify\Events\NotifyCampaignWebhookEvent;
// (campaignId, status, payload)
```

Registre listeners no `EventServiceProvider`:

```php
protected $listen = [
    \RiseTechApps\Notify\Events\NotifySentEvent::class => [
        App\Listeners\LogNotificacaoEnviada::class,
    ],
    \RiseTechApps\Notify\Events\NotifyWebhookEvent::class => [
        App\Listeners\AtualizarStatusNotificacao::class,
    ],
    \RiseTechApps\Notify\Events\NotifyCampaignWebhookEvent::class => [
        App\Listeners\AtualizarProgressoCampanha::class,
    ],
];
```

---

## Configurações de driver

Gerencie as credenciais de cada canal pelo próprio `NotifyQuery`, sem acessar o painel
do servidor. O ponto de entrada é **por canal**: `NotifyQuery::server()->{canal}()->config()`.
O **driver** é informado com `->driver('twilio')` e as credenciais por setters fluentes,
terminando em `->save()`.

```php
use RiseTechApps\Notify\NotifyQuery;
```

### Descobrir e listar

```php
// O que dá pra cadastrar: cada canal com default_driver + credential_fields
NotifyQuery::server()->drivers();

// Configs já cadastradas (só as ativas)
NotifyQuery::server()->configurations();          // todas
NotifyQuery::server()->configurations('sms');     // só SMS

// Pelo recurso do canal (equivalente): sem id lista, com id detalha
NotifyQuery::server()->sms()->config()->get();          // lista as configs de SMS
NotifyQuery::server()->sms()->config('uuid')->get();    // detalhe (metadados + credential_keys)
```

### Criar — SMS

Drivers: `mobizon` (padrão), `clicksend`, `twilio`.

```php
// Mobizon (driver padrão do canal)
NotifyQuery::server()->sms()->config()
    ->driver('mobizon')
    ->label('Mobizon')
    ->key('xxxxxxxxxxxxxxxx')
    ->apiServer('xx')          // ex.: "61" (subdomínio da API Mobizon)
    ->asDefault()
    ->save();

// ClickSend
NotifyQuery::server()->sms()->config()
    ->driver('clicksend')
    ->label('ClickSend Produção')
    ->username('user@empresa.com')
    ->apiKey('xxxxxxxxxxxxxxxx')
    ->from('Empresa')
    ->save();

// Twilio
NotifyQuery::server()->sms()->config()
    ->driver('twilio')
    ->label('Twilio Principal')
    ->sid('ACxxxxxxxxxxxxxxxx')
    ->token('xxxxxxxxxxxxxxxx')
    ->from('+5511999999999')
    ->save();
```

### Criar — Email

Drivers: `smtp` (padrão), `ses`, `mailgun`, `sendgrid`, `postmark`, `resend`.

```php
// SMTP — NÃO tem credenciais próprias: usa o config/mail.php da sua app
NotifyQuery::server()->mail()->config()
    ->driver('smtp')
    ->label('SMTP Produção')
    ->asDefault()
    ->save();

// Mailgun (domain / secret / endpoint)
NotifyQuery::server()->mail()->config()
    ->driver('mailgun')
    ->label('Mailgun')
    ->domain('mg.empresa.com')
    ->secret('key-xxxxxxxxxxxxxxxx')
    ->endpoint('api.mailgun.net')   // ou api.eu.mailgun.net
    ->save();

// Resend
NotifyQuery::server()->mail()->config()->driver('resend')->label('Resend')->apiKey('re_xxxxxxxx')->save();

// SendGrid
NotifyQuery::server()->mail()->config()->driver('sendgrid')->label('SendGrid')->apiKey('SG.xxxxxxxx')->save();

// Amazon SES
NotifyQuery::server()->mail()->config()
    ->driver('ses')
    ->label('SES us-east-1')
    ->key('AKIAXXXXXXXXXXXXXXXX')
    ->secret('xxxxxxxxxxxxxxxx')
    ->region('us-east-1')
    ->save();

// Postmark
NotifyQuery::server()->mail()->config()->driver('postmark')->label('Postmark')->token('xxxxxxxx')->save();
```

### Criar — Push / APNS

```php
// FCM — a partir do arquivo do Service Account: o client LÊ o arquivo e envia
// o conteúdo (JSON parseado) em credentials_json (não o caminho)
NotifyQuery::server()->push()->config()
    ->driver('fcm')
    ->label('FCM-PUSHER')
    ->credentialsFile(storage_path('app/google-services.json'))
    ->asDefault()
    ->save();

// FCM — Service Account inline (array ou string JSON), sem arquivo em disco
NotifyQuery::server()->push()->config()
    ->driver('fcm')
    ->label('FCM Cliente X')
    ->credentialsJson($serviceAccountArray)   // array OU string JSON
    ->save();

// APNS (iOS)
NotifyQuery::server()->apns()->config()
    ->driver('apns')
    ->label('APNS iOS')
    ->keyPath('/path/to/AuthKey.p8')
    ->keyId('XXXXXXXXXX')
    ->teamId('XXXXXXXXXX')
    ->bundleId('com.empresa.app')
    ->production(true)
    ->save();
```

### Criar — Mensageiros / WebSocket

```php
// Telegram
NotifyQuery::server()->telegram()->config()
    ->driver('telegram')
    ->label('Telegram Bot')
    ->botToken('123456789:AAxxxxxxxxxx')
    ->save();

// Slack
NotifyQuery::server()->slack()->config()
    ->driver('slack')
    ->label('Slack Workspace')
    ->webhookUrl('https://hooks.slack.com/services/xxx/yyy/zzz')
    ->defaultChannel('#geral')
    ->save();

// Discord (driver "webhook": webhook_url / username / avatar_url)
NotifyQuery::server()->discord()->config()
    ->driver('webhook')
    ->label('Discord Server')
    ->webhookUrl('https://discord.com/api/webhooks/xxx/yyy')
    ->defaultUsername('NotifyBot')
    ->avatarUrl('https://app.com/bot-avatar.png')
    ->save();

// Teams (driver "webhook": webhook_url / theme_color)
NotifyQuery::server()->teams()->config()
    ->driver('webhook')
    ->label('Teams Canal Engenharia')
    ->webhookUrl('https://outlook.office.com/webhook/xxx')
    ->themeColor('0078D4')
    ->save();

// Pusher (WebSocket)
NotifyQuery::server()->websocket()->config()
    ->driver('pusher')
    ->label('Pusher Produção')
    ->appId('xxxxxxxx')
    ->key('xxxxxxxxxxxxxxxx')
    ->secret('xxxxxxxxxxxxxxxx')
    ->cluster('mt1')
    ->save();

// Webhook (driver "generic")
NotifyQuery::server()->webhook()->config()
    ->driver('generic')
    ->label('ERP Empresa')
    ->defaultUrl('https://erp.empresa.com/api/eventos')
    ->authType('bearer')
    ->authToken('token-secreto')
    ->timeout(15)
    ->injectMetadata(true)
    ->save();
```

> O `config()` está em **todos** os recursos de canal — `server()->slack()->config()` faz a
> credencial e `server()->slack()->get()` traz o histórico, sem conflito. Use
> `NotifyQuery::server()->drivers()` para descobrir os drivers válidos e os
> `credential_fields` de cada canal. O `->driver()` é obrigatório ao criar.

### Detalhe, atualizar, definir padrão e remover

O mesmo `->save()` cria ou atualiza, conforme o `id`: **sem id** (`config()`) faz cadastro
(POST); **com id** (`config('uuid')`) faz atualização (PUT, merge parcial das credenciais).
Na atualização o driver não muda.

```php
// Detalhe (retorna só as chaves das credenciais, nunca os valores)
NotifyQuery::server()->push()->config('config-uuid')->get();

// Atualizar — é o mesmo save(), só que com id. Merge parcial: só o que enviar muda.
NotifyQuery::server()->mail()->config('config-uuid')
    ->label('Novo nome')
    ->password('nova-senha')   // só a senha muda; o resto permanece
    ->asDefault()
    ->save();

// Definir como padrão do canal
NotifyQuery::server()->sms()->config('config-uuid')->setDefault();

// Remover
NotifyQuery::server()->sms()->config('config-uuid')->delete();
```

### Aliases de canal (Telegram e Slack)

Cadastre **nomes lógicos** (ex.: `equipe`, `geral`, `help`) por configuração e, no envio,
mande só o nome — o servidor resolve para o destino real (chat_id no Telegram; ID/`#canal`
no Slack). Os aliases ficam **por configuração** (cada bot/workspace tem o seu mapa), e são
gerenciados via `->config('uuid')->channels()`:

```php
use RiseTechApps\Notify\NotifyQuery;

// Listar os aliases da config (mapa nome => destino)
NotifyQuery::server()->telegram()->config('config-uuid')->channels()->all();

// Criar / atualizar (idempotente — se o nome existir, sobrescreve)
NotifyQuery::server()->telegram()->config('config-uuid')->channels()
    ->set('equipe', '-1003142488245');

NotifyQuery::server()->slack()->config('config-uuid')->channels()
    ->set('equipe', 'C0BAX5KS8KE');

// Remover
NotifyQuery::server()->telegram()->config('config-uuid')->channels()->remove('equipe');
```

No **envio**, basta usar o nome no alvo — nada de guardar chat_ids/IDs de canal:

```php
// Telegram
(new NotifyTelegram)->chatId('equipe')->message('Olá, equipe!');

// Slack
(new NotifySlack)->channel('equipe')->message('Olá, equipe!');
```

> **Fallthrough:** se o valor não for um alias cadastrado, é usado como está — continua
> valendo passar o destino direto (`->chatId('-1003142488245')`, `->channel('C0BAX5KS8KE')`
> ou `'#geral'`/`'@meucanal'`). Edição/exclusão de mensagem no Telegram também funciona
> quando a mensagem foi enviada por alias (o servidor re-resolve). O `default_channel` do
> Slack é sempre canal literal (não passa por alias).

### Usando uma config específica em um envio

Depois de criar a config no servidor e ter o UUID dela, passe o `config_id` na mensagem:

```php
// Notificação individual
public function toNotifySms($notifiable): NotifySms
{
    return (new NotifySms)
        ->to($notifiable->phone)
        ->content('Mensagem via Twilio')
        ->configId('uuid-da-config-twilio');  // sobrescreve a config padrão
}

// Campanha
NotifyCampaignBuilder::sms()
    ->name('Promo')
    ->content('Olá {name}!')
    ->contacts($contacts)
    ->configId('uuid-da-config-twilio')       // sobrescreve a config padrão
    ->send();
```

---

## Licença

MIT — veja [LICENSE.md](LICENSE.md) para detalhes.

&copy; 2026 [Rise Tech Apps](https://risetech.com.br)
