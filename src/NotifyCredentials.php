<?php

namespace RiseTechApps\Notify;

/**
 * Helper para montar credenciais inline por driver.
 *
 * Use quando quiser sobrescrever a configuração padrão do servidor
 * em um disparo específico, sem precisar criar uma config salva (config_id).
 * O servidor usa essas credenciais diretamente, sem buscar no banco.
 *
 * Só o NotifyCampaignBuilder aceita credenciais inline via ->credentials().
 * Nas notificações individuais (NotifySms, NotifyMail, ...) use ->configId().
 *
 * ──────────────────────────────────────────────────────────────────────────────
 * Exemplos:
 * ──────────────────────────────────────────────────────────────────────────────
 *
 *   // Campanha SMS via Twilio específico
 *   NotifyCampaignBuilder::sms()
 *       ->name('Promo')
 *       ->content('Olá {name}!')
 *       ->contacts([...])
 *       ->credentials(NotifyCredentials::twilio('ACxxx', 'auth-token', '+15551234567'))
 *       ->send();
 *
 *   // Campanha de email via Resend
 *   NotifyCampaignBuilder::email()
 *       ->name('Newsletter')
 *       ->subject('Novidades')
 *       ->contacts([...])
 *       ->credentials(NotifyCredentials::resend('re_xxxxxxxxxxxx'))
 *       ->send();
 */
class NotifyCredentials
{
    // ── SMS ───────────────────────────────────────────────────────────────────

    /**
     * Twilio SMS.
     *
     * @param string $sid   Account SID (ACxxxxxxxxxxxxx)
     * @param string $token Auth Token
     * @param string $from  Número de origem no formato E.164: +15551234567
     */
    public static function twilio(string $sid, string $token, string $from): array
    {
        return [
            'driver'      => 'twilio',
            'credentials' => [
                'sid'   => $sid,
                'token' => $token,
                'from'  => $from,
            ],
        ];
    }

    /**
     * ClickSend SMS.
     *
     * @param string $username Usuário da conta ClickSend
     * @param string $apiKey   API Key
     * @param string $from     Remetente (número ou alfanumérico)
     */
    public static function clicksend(string $username, string $apiKey, string $from): array
    {
        return [
            'driver'      => 'clicksend',
            'credentials' => [
                'username' => $username,
                'api_key'  => $apiKey,
                'from'     => $from,
            ],
        ];
    }

    /**
     * Mobizon SMS.
     *
     * @param string $key        API Key da Mobizon
     * @param string $apiServer  Servidor da API. Default: api.mobizon.com.br
     */
    public static function mobizon(string $key, string $apiServer = 'api.mobizon.com.br'): array
    {
        return [
            'driver'      => 'mobizon',
            'credentials' => [
                'key'        => $key,
                'api_server' => $apiServer,
            ],
        ];
    }

    // ── Email ─────────────────────────────────────────────────────────────────

    /**
     * SMTP.
     *
     * Atenção: no contrato atual o driver `smtp` não declara `credential_fields` —
     * o servidor usa o próprio `config/mail.php` dele. Só use este helper se o seu
     * servidor aceitar SMTP inline; caso contrário prefira ses/mailgun/resend/etc.
     *
     * @param string $host
     * @param string $username
     * @param string $password
     * @param int    $port        Default: 587
     * @param string $encryption  tls | ssl. Default: tls
     */
    public static function smtp(
        string $host,
        string $username,
        string $password,
        int    $port = 587,
        string $encryption = 'tls'
    ): array {
        return [
            'driver'      => 'smtp',
            'credentials' => [
                'host'       => $host,
                'port'       => $port,
                'encryption' => $encryption,
                'username'   => $username,
                'password'   => $password,
            ],
        ];
    }

    /**
     * Mailgun.
     *
     * @param string $domain    Domínio registrado no Mailgun
     * @param string $secret    API Key do Mailgun
     * @param string $endpoint  api.mailgun.net (padrão) ou api.eu.mailgun.net (Europa)
     */
    public static function mailgun(string $domain, string $secret, string $endpoint = 'api.mailgun.net'): array
    {
        return [
            'driver'      => 'mailgun',
            'credentials' => [
                'domain'   => $domain,
                'secret'   => $secret,
                'endpoint' => $endpoint,
            ],
        ];
    }

    /**
     * Resend.
     *
     * @param string $apiKey  API Key do Resend (re_xxxxxxxxxxxx)
     */
    public static function resend(string $apiKey): array
    {
        return [
            'driver'      => 'resend',
            'credentials' => [
                'api_key' => $apiKey,
            ],
        ];
    }

    /**
     * SendGrid.
     *
     * @param string $apiKey  API Key do SendGrid
     */
    public static function sendgrid(string $apiKey): array
    {
        return [
            'driver'      => 'sendgrid',
            'credentials' => [
                'api_key' => $apiKey,
            ],
        ];
    }

    /**
     * Amazon SES.
     *
     * @param string $key     AWS Access Key ID
     * @param string $secret  AWS Secret Access Key
     * @param string $region  Default: us-east-1
     */
    public static function ses(string $key, string $secret, string $region = 'us-east-1'): array
    {
        return [
            'driver'      => 'ses',
            'credentials' => [
                'key'    => $key,
                'secret' => $secret,
                'region' => $region,
            ],
        ];
    }

    /**
     * Postmark.
     *
     * @param string $token  Server Token do Postmark
     */
    public static function postmark(string $token): array
    {
        return [
            'driver'      => 'postmark',
            'credentials' => [
                'token' => $token,
            ],
        ];
    }

    // ── Push ──────────────────────────────────────────────────────────────────

    /**
     * Firebase Cloud Messaging (FCM).
     *
     * O servidor espera o Service Account inteiro em `credentials_json` — não o caminho
     * de um arquivo, que não existe na máquina dele.
     *
     * @param array|string $serviceAccount Conteúdo do Service Account: array já decodificado
     *                                     ou a string JSON crua.
     *
     * @throws \InvalidArgumentException Se a string JSON for inválida.
     */
    public static function fcm(array|string $serviceAccount): array
    {
        if (is_string($serviceAccount)) {
            $decoded = json_decode($serviceAccount, true);

            if (!is_array($decoded)) {
                throw new \InvalidArgumentException('fcm(): invalid JSON string provided');
            }

            $serviceAccount = $decoded;
        }

        return [
            'driver'      => 'fcm',
            'credentials' => [
                'credentials_json' => $serviceAccount,
            ],
        ];
    }

    /**
     * FCM lendo o Service Account de um arquivo local — o conteúdo é embutido em
     * `credentials_json`. Ex.: NotifyCredentials::fcmFile(storage_path('app/fcm.json')).
     *
     * @throws \InvalidArgumentException Se o arquivo não existir/não for legível ou não for JSON válido.
     */
    public static function fcmFile(string $path): array
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new \InvalidArgumentException("Service Account file not found or unreadable: {$path}");
        }

        $json = json_decode($contents, true);

        if (!is_array($json)) {
            throw new \InvalidArgumentException("Service Account file is not valid JSON: {$path}");
        }

        return self::fcm($json);
    }

    /**
     * Apple Push Notification Service (APNS).
     *
     * @param string $keyPath    Caminho para o arquivo .p8 no servidor
     * @param string $keyId      Key ID da chave APNs
     * @param string $teamId     Team ID da Apple Developer Account
     * @param string $bundleId   Bundle ID do app iOS
     * @param bool   $production false = sandbox, true = produção
     */
    public static function apns(
        string $keyPath,
        string $keyId,
        string $teamId,
        string $bundleId,
        bool   $production = false
    ): array {
        return [
            'driver'      => 'apns',
            'credentials' => [
                'key_path'   => $keyPath,
                'key_id'     => $keyId,
                'team_id'    => $teamId,
                'bundle_id'  => $bundleId,
                'production' => $production,
            ],
        ];
    }

    // ── Mensageiros ───────────────────────────────────────────────────────────

    /**
     * Telegram Bot.
     *
     * @param string $botToken  Token do bot (obtido via @BotFather)
     */
    public static function telegram(string $botToken): array
    {
        return [
            'driver'      => 'telegram',
            'credentials' => [
                'bot_token' => $botToken,
            ],
        ];
    }

    /**
     * Slack.
     *
     * @param string      $botToken       OAuth Bot Token (xoxb-...)
     * @param string|null $webhookUrl     Incoming Webhook URL (alternativa ao bot token)
     * @param string      $defaultChannel Canal padrão. Default: #general
     */
    public static function slack(
        string  $botToken = '',
        ?string $webhookUrl = null,
        string  $defaultChannel = '#general'
    ): array {
        return [
            'driver'      => 'slack',
            'credentials' => array_filter([
                'bot_token'       => $botToken ?: null,
                'webhook_url'     => $webhookUrl,
                'default_channel' => $defaultChannel,
            ], fn($v) => !is_null($v)),
        ];
    }

    /**
     * Discord Webhook.
     *
     * @param string      $webhookUrl  URL do webhook do Discord
     * @param string|null $username    Nome exibido. Default: nome do app
     * @param string|null $avatarUrl   URL do avatar do bot
     */
    public static function discord(
        string  $webhookUrl,
        ?string $username = null,
        ?string $avatarUrl = null
    ): array {
        return [
            'driver'      => 'webhook',
            'credentials' => array_filter([
                'webhook_url' => $webhookUrl,
                'username'    => $username,
                'avatar_url'  => $avatarUrl,
            ], fn($v) => !is_null($v)),
        ];
    }

    /**
     * Microsoft Teams Webhook.
     *
     * @param string $webhookUrl   URL do conector do Teams
     * @param string $themeColor   Cor do card em hex sem #. Default: 0076D7
     */
    public static function teams(string $webhookUrl, string $themeColor = '0076D7'): array
    {
        return [
            'driver'      => 'webhook',
            'credentials' => [
                'webhook_url' => $webhookUrl,
                'theme_color' => $themeColor,
            ],
        ];
    }

    // ── WebSocket ─────────────────────────────────────────────────────────────

    /**
     * Pusher (ou Soketi/Laravel Reverb com protocolo Pusher).
     *
     * @param string      $appId    App ID
     * @param string      $key      App Key
     * @param string      $secret   App Secret
     * @param string      $cluster  Cluster. Default: mt1
     * @param string|null $host     Host customizado (ex: soketi.meuapp.com)
     * @param int|null    $port     Porta customizada. Default: 6001 se host informado
     * @param string|null $scheme   http | https
     */
    public static function pusher(
        string  $appId,
        string  $key,
        string  $secret,
        string  $cluster = 'mt1',
        ?string $host = null,
        ?int    $port = null,
        ?string $scheme = null
    ): array {
        return [
            'driver'      => 'pusher',
            'credentials' => array_filter([
                'app_id'  => $appId,
                'key'     => $key,
                'secret'  => $secret,
                'cluster' => $cluster,
                'host'    => $host,
                'port'    => $port,
                'scheme'  => $scheme,
            ], fn($v) => !is_null($v)),
        ];
    }
}
