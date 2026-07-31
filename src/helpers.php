<?php

use RiseTechApps\Notify\NotifyQuery;

if (!function_exists('notifyQuery')) {
    function notifyQuery(): \RiseTechApps\Notify\ServerQuery
    {
        return NotifyQuery::server();
    }
}
