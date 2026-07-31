<?php

arch('src')
    ->expect('RiseTechApps\Notify\Query')
    ->and('RiseTechApps\Notify\Channel')
    ->toUseStrictTypes()
    ->ignoring('RiseTechApps\Notify\Channel\NotifyChannel');
