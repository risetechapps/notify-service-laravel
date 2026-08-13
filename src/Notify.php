<?php

namespace RiseTechApps\Notify;

class Notify
{
    /**
     * URL base do servidor NotifyKit.
     *
     * Ponto único de definição — alterado apenas aqui, na fonte do package.
     * Não exposto na config para impedir sobrescrita pelo usuário final.
     */
    public const BASE_URL = 'https://notifykit.app.br';
}
