<?php

use Nuwber\Events\BroadcastFactory;
use Nuwber\Events\MessageFactory;

if (!function_exists('fire')) {

    function fire(string $event, array $payload)
    {
        app(BroadcastFactory::class)->send(
            $event,
            app(MessageFactory::class)->make($event, $payload)
        );
    }

}
