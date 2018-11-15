<?php

use Butik\Events\BroadcastFactory;
use Butik\Events\MessageFactory;

if (!function_exists('fire')) {

    function fire(string $event, array $payload)
    {
        app(BroadcastFactory::class)->send(
            app(MessageFactory::class)->make($event, $payload)
        );
    }

}
