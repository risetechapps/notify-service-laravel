<?php

use RiseTechApps\Notify\NotifyFacade;
use RiseTechApps\Notify\NotifyQuery;

it('facade returns NotifyQuery instance', function () {
    $accessor = (new ReflectionClass(NotifyFacade::class))->getMethod('getFacadeAccessor');
    $accessor->setAccessible(true);
    $serviceName = $accessor->invoke(null);

    expect($serviceName)->toBe(NotifyQuery::class);
});

it('Notify facade resolves to NotifyQuery', function () {
    $resolved = app(NotifyQuery::class);

    expect($resolved)->toBeInstanceOf(NotifyQuery::class);
});
