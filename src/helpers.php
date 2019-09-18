<?php

use Nuwber\Events\Event\Publisher;
use Nuwber\Events\Event\ShouldPublish;
use Nuwber\Events\MessageFactory;

if (!function_exists('fire')) {

    function fire(string $event, array $payload)
    {
        app(Publisher::class)->send($event, $payload);
    }

}

if (!function_exists('publish')) {

    function publish($event, $payload = [])
    {
        app(Publisher::class)->publish($event, $payload);
    }

}
