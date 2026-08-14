# Changelog

Todas as mudanças notáveis para o pacote `risetechapps/notify-service-for-laravel` serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), e este projeto adere ao [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - 2026-07-27

### Segurança

- **Validação de assinatura nos webhooks recebidos**: novo middleware `VerifyNotifySignature`, aplicado automaticamente às rotas de webhook. Valida o header `X-Notify-Signature` (`t=<timestamp>,v1=<hmac>`) recalculando o HMAC-SHA256 de `{timestamp}.{corpo bruto}` com `notify.webhook_secret`, comparando via `hash_equals()` e recusando payloads fora da janela anti-replay (`notify.webhook_tolerance`, default 300s). Antes disso o endpoint era público — qualquer um podia forjar um callback e disparar os listeners da aplicação.
  - **Breaking**: defina `NOTIFY_SERVICE_WEBHOOK_SECRET` ou os callbacks passam a retornar `403`. Para desligar a validação, use `notify.webhook_verify=false`.
  - Alias `notify.signature` registrado para quem declara as rotas manualmente.
  - Aceita múltiplos `v1` no mesmo header, permitindo rotação de segredo sem downtime.

### Adicionado

- **Chaves de config das rotas de webhook**: `notify.routes`, `notify.routes_prefix` e `notify.routes_middleware` agora existem em `config/config.php` — eram lidas pelo ServiceProvider mas não estavam publicadas.

- **Helper `notifyQuery()`**: Nova função global que retorna `ServerQuery`, alternativa mais concisa a `NotifyQuery::server()`.
- **Preview de e-mail**: Métodos `preview()` e `previewLink()` em `ServerChannelQuery` para visualizar o HTML do e-mail enviado via API do servidor.
- **Validação de campos obrigatórios**: `send()` e `sendHtml()` do `NotifyCampaignBuilder` agora validam name, content/subject e contatos antes do HTTP call, lançando `\InvalidArgumentException`.
- **`NotifyCredentials::clicksend()`**: Fábrica do driver `clicksend` (`username`, `api_key`, `from`), que existia no contrato do servidor mas não tinha helper.
- **`NotifyCredentials::fcmFile()`**: Lê o Service Account de um arquivo local e embute o conteúdo em `credentials_json`.

### Alterado

- **`scheduled_at` em campanhas**: Agora é convertido automaticamente para ISO 8601 com timezone (`config('app.timezone')`) antes de enviar ao servidor.
- **Tratamento de erros**: Canais e `NotifyCampaignBuilder` agora retornam o body de erro da API em vez de `null`/`[]`. Exceções reais (timeout, etc.) retornam `['error' => mensagem]`.
- **`logglyInfo()`/`logglyError()` removidos**: Substituídos por `\Illuminate\Support\Facades\Log::info()` e `Log::error()` em todos os 10 canais.
- **`NotifyFacade`**: Agora aponta para `NotifyQuery::class` em vez de `Notify::class` (que só possui constantes).
- **`resolveContacts()`**: Substituído `chunk(500)` por `lazy(500)` para melhor eficiência de memória durante iteração.
- **`sendHtml()`**: Agora valida se o arquivo existe e é legível antes de ler, lançando exceção em vez de enviar `false`.
- **`ServerDriverConfig::credentialsJson()`**: Lança `\InvalidArgumentException` se a string JSON for inválida, em vez de passar string crua.
- **`NotifyCredentials::fcm()`**: Assinatura alterada para `fcm(array|string $serviceAccount)`. Antes montava `project_id` + `credentials_file` (caminho), que o servidor não aceita mais — agora envia o Service Account inteiro em `credentials_json`, alinhado ao `ServerDriverConfig`. **Breaking** para quem chamava `fcm($projectId, $path)`: troque por `fcmFile($path)`.

### Removido

- **`NotifyCredentials::zenvia()`**: O driver `zenvia` foi removido do servidor; o helper montava credenciais que hoje resultam em erro de validação. **Breaking** para quem ainda o usava — migre para `mobizon`, `clicksend` ou `twilio`.

### Documentação

- **README**: documentados o envio multicanal (`NotifyQuery::multi()`), as credenciais inline (`NotifyCredentials`), o template HTML próprio em campanhas (`sendHtml()`), o preview de e-mail (`preview()`/`previewLink()`), o helper `notifyQuery()`, o filtro `untagged()`, o retorno dos canais em caso de erro, as validações que lançam `\InvalidArgumentException` e a conversão de `scheduledAt` para ISO 8601 com timezone. Índice atualizado.
- **`NotifyCredentials`**: docblock corrigido — os exemplos mostravam `->credentials()` em classes de mensagem individuais, onde o método não existe (lá o correto é `->configId()`). Também documenta que o driver `smtp` não declara `credential_fields` no contrato atual.

## [1.2.0] - 2026-03-17
- Implementado envio de arquivo html nas campanhas.

## [1.1.0] - 2026-03-12

### Adicionado

- **`NotifyCampaignBuilder`**: Nova classe fluent para disparo de campanhas em massa de SMS e Email, totalmente independente do sistema de notificações do Laravel.
    - Suporte a até 10.000 contatos por campanha com inserção em lotes de 500.
    - Três fontes de contatos: array direto (`->contacts()`), query Eloquent com chunks (`->fromQuery()`), e Collection (`->fromCollection()`).
    - Rate limiting configurável via `->ratePerMinute()` (padrão: 60, máx: 600).
    - Agendamento futuro via `->scheduledAt()`.
    - Campo do contato correto por canal: `phone` para SMS, `email` para Email.

- **`NotifyQuery`**: Nova classe de consultas com dois modos — banco local e API do servidor.
    - Consultas locais: `logs()`, `logsFor()`, `findLog()`, `campaigns()`, `findCampaign()`, `campaignContacts()`.
    - Consultas no servidor via `server()`: listagem e detalhes de notificações, timeline de eventos, listagem e detalhes de campanhas, contatos de campanha com busca e filtro.
    - Classes fluent internas: `ServerNotificationQuery`, `ServerCampaignQuery`, `ServerCampaignContactQuery`.

- **Rastreamento automático de envios**: Todos os 10 canais agora criam um `NotifyLog` automaticamente antes e após cada envio, sem necessidade de alteração nas classes de notificação do usuário.

- **Migrations**: Criadas as tabelas `notify_logs`, `notify_campaigns` e `notify_campaign_contacts`.

- **Models**: Adicionados `NotifyLog`, `NotifyCampaign` e `NotifyCampaignContact` com relacionamentos, casts, scopes e métodos de ciclo de vida (`markAsSent`, `markAsDelivered`, `markAsFailed`, `syncFromWebhook`).

- **`NotifyWebhookController`**: Controller para receber callbacks de status do servidor.
    - `notification()` — atualiza status de notificações individuais no banco local.
    - `campaign()` — atualiza contadores, status e contatos individuais de campanhas.

- **Rotas automáticas de webhook**: Registradas via `ServiceProvider` quando `notify.routes = true`.
    - `POST {prefix}/webhook` — notificações individuais.
    - `POST {prefix}/webhook/campaign` — campanhas.
    - Prefixo e middleware configuráveis via `config/notify.php` ou variáveis de ambiente.

- **Novos canais de notificação individual**: `notify.push`, `notify.apns`, `notify.telegram`, `notify.slack`, `notify.discord`, `notify.teams`, `notify.websocket`, `notify.webhook` (além dos já existentes `notify.sms` e `notify.mail`).

- **Novas classes de mensagem**:
    - `NotifyPush` — FCM com suporte a token, topic, título, body, imagem e data.
    - `NotifyApns` — APNS com badge, sound, category, threadId, collapseId e silent push.
    - `NotifyTelegram` — com parse_mode, imagem e inline buttons.
    - `NotifySlack` — com channel, color, title e fields.
    - `NotifyDiscord` — com embed completo: color, thumbnail, image, footer e fields.
    - `NotifyTeams` — com card adaptativo: facts e actions.
    - `NotifyWebSocket` — Pusher com suporte a canais private e presence.
    - `NotifyWebhook` — HTTP genérico com auth bearer, basic, api_key e hmac.

- **`README.md`**: Documentação completa com exemplos de todos os canais, campanhas, rastreamento, webhook receiver, consultas locais e no servidor, modelos e eventos.

### Alterado

- **`config/notify.php`**: Adicionadas as chaves `routes`, `routes_prefix` e `routes_middleware` para controle das rotas de webhook.
- **`NotifyServiceProvider`**: Atualizado para registrar todos os 10 canais individuais e as rotas de webhook condicionalmente.

### Removido

- **`Channel/Campaign/`** e **`Message/Campaign/`**: Removidas as classes de campanha baseadas no sistema de notificações do Laravel (`notify.sms.campaign`, `notify.mail.campaign`) em favor do `NotifyCampaignBuilder`, que oferece uma API mais adequada para disparos em massa.

---

## [1.0.0] - 2025-12-29

### Adicionado

- **Funcionalidade Principal**: Implementação do pacote `Notify Service for Laravel` para integração com a plataforma NotifyKit.
- **Canais de Notificação**: Adicionados os canais `notify.mail` e `notify.sms` ao sistema de Notificações do Laravel.
- **Mensagens Ricas**: Classes `NotifyMail` e `NotifySms` para construção de mensagens ricas em conteúdo (e-mail) e concisas (SMS).
- **Eventos**: Disparo de eventos (`NotifySendingEvent`, `NotifySentEvent`, `NotifyFailedEvent`) para monitoramento do ciclo de vida da notificação.
- **Configuração**: Publicação do arquivo de configuração `config/notify.php` para chave de API.
- **Estrutura de Pacote**: Arquivos de estrutura inicial, incluindo `composer.json`, `LICENSE.md` e `CONTRIBUTING.md`.
