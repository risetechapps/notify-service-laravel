<?php

/*
 * You can place your custom package configuration in here.
 */
return [
    'key' => env('NOTIFY_SERVICE_KEY', ""),

    // URL de callback informada ao servidor nos envios (para onde ELE manda o status).
    'webhook' => env('NOTIFY_SERVICE_WEBHOOK', ""),

    /*
    |--------------------------------------------------------------------------
    | Recebimento dos callbacks do servidor
    |--------------------------------------------------------------------------
    |
    | O pacote registra POST {prefix}/webhook e POST {prefix}/webhook/campaign,
    | que apenas disparam NotifyWebhookEvent / NotifyCampaignWebhookEvent.
    |
    */

    // Registra as rotas de webhook automaticamente. Desligue se preferir declarar
    // as rotas manualmente no seu routes/api.php apontando para NotifyWebhookController.
    'routes' => env('NOTIFY_SERVICE_ROUTES', true),

    // Prefixo das rotas: resulta em /notify/webhook e /notify/webhook/campaign.
    'routes_prefix' => env('NOTIFY_SERVICE_ROUTES_PREFIX', 'notify'),

    // Middlewares do grupo. A validação de assinatura é sempre aplicada por cima
    // deste array (controlada por 'webhook_verify'), não precisa ser listada aqui.
    'routes_middleware' => ['api'],

    // Segredo compartilhado usado para validar o header X-Notify-Signature.
    // Obtido no painel do NotifyKit. Sem ele os callbacks são recusados com 403.
    'webhook_secret' => env('NOTIFY_SERVICE_WEBHOOK_SECRET', ""),

    // Valida a assinatura HMAC dos callbacks recebidos. Só desligue em ambiente
    // local — com false qualquer um pode forjar um callback e disparar seus listeners.
    'webhook_verify' => env('NOTIFY_SERVICE_WEBHOOK_VERIFY', true),

    // Janela anti-replay em segundos: recusa callbacks cujo timestamp assinado
    // esteja fora deste intervalo. 0 desliga a checagem de tempo.
    'webhook_tolerance' => env('NOTIFY_SERVICE_WEBHOOK_TOLERANCE', 300),

    'sms' => [
        // config_id default (UUID ou label, ex.: "Twilio Principal") usado quando a
        // notificação não passa ->configId(). null = usa a config default do servidor.
        'config_id' => env('NOTIFY_SMS_CONFIG_ID'),

        // Tags aplicadas a todo SMS, mescladas com as tags definidas na notificação.
        'tags' => [],
    ],

    'mail' => [
        // Remetente default. A notificação pode sobrescrever com ->from($email, $name).
        'from' => [
            'address' => env('NOTIFY_MAIL_FROM_ADDRESS'),
            'name'    => env('NOTIFY_MAIL_FROM_NAME'),
        ],

        // Nome do app/marca exibido no template. Cai para config('app.name') se null.
        'app_name' => env('NOTIFY_MAIL_APP_NAME'),

        // Tema default do template do servidor.
        'theme' => env('NOTIFY_MAIL_THEME', 'default'),

        // config_id default (UUID ou label da credencial SMTP, multi-tenant).
        // Sobrescrito pelo ->configId() da notificação. null = config default do servidor.
        'config_id' => env('NOTIFY_MAIL_CONFIG_ID'),

        // Tags aplicadas a todo e-mail, mescladas com as tags definidas na notificação.
        'tags' => [],
    ],

    'push' => [
        // config_id default (UUID ou label da credencial FCM).
        // Sobrescrito pelo ->configId() da notificação. null = config default do servidor.
        'config_id' => env('NOTIFY_PUSH_CONFIG_ID'),

        // Tag default. No push a tag é uma STRING única (FCM grouping tag + etiqueta de
        // histórico) — não aceita array. A ->tag() da notificação sobrescreve esta.
        'tag' => env('NOTIFY_PUSH_TAG'),
    ],

    'apns' => [
        // config_id default (UUID ou label da credencial APNS).
        // Sobrescrito pelo ->configId() da notificação. null = config default do servidor.
        'config_id' => env('NOTIFY_APNS_CONFIG_ID'),

        // Tags aplicadas a todo push APNS, mescladas com as tags definidas na notificação.
        'tags' => [],
    ],

    'telegram' => [
        // config_id default (UUID ou label do bot Telegram).
        // Sobrescrito pelo ->configId() da notificação. null = config default do servidor.
        'config_id' => env('NOTIFY_TELEGRAM_CONFIG_ID'),

        // Tags aplicadas a todo envio Telegram, mescladas com as tags da notificação.
        'tags' => [],
    ],

    'slack' => [
        // config_id default (UUID ou label da credencial Slack — Bot Token ou Incoming Webhook).
        // Sobrescrito pelo ->configId() da notificação. null = config default do servidor.
        'config_id' => env('NOTIFY_SLACK_CONFIG_ID'),

        // Tags aplicadas a todo envio Slack, mescladas com as tags da notificação.
        'tags' => [],
    ],

    'discord' => [
        // config_id default (UUID ou label da credencial Discord).
        // Sobrescrito pelo ->configId() da notificação. null = config default do servidor.
        'config_id' => env('NOTIFY_DISCORD_CONFIG_ID'),

        // Tags aplicadas a todo envio Discord, mescladas com as tags da notificação.
        'tags' => [],
    ],

    'teams' => [
        // config_id default (UUID ou label da credencial Teams).
        // Sobrescrito pelo ->configId() da notificação. null = config default do servidor.
        'config_id' => env('NOTIFY_TEAMS_CONFIG_ID'),

        // Tags aplicadas a todo envio Teams, mescladas com as tags da notificação.
        'tags' => [],
    ],

    'websocket' => [
        // config_id default (UUID ou label da credencial WebSocket — ex.: Pusher).
        // Sobrescrito pelo ->configId() da notificação. null = config default do servidor.
        'config_id' => env('NOTIFY_WEBSOCKET_CONFIG_ID'),

        // Tags aplicadas a todo evento WebSocket, mescladas com as tags da notificação.
        'tags' => [],
    ],
];
