<?php

use Nuwber\Events\Publisher;
use Nuwber\Events\MessageFactory;

if (!function_exists('fire')) {
    function fire(string $event, array $payload)
    {
        app(Publisher::class)->send($event, $payload);
    }
}
