<?php

use Butik\Events\BroadcastFactory;
use Butik\Events\MessageFactory;

if (!function_exists('fire')) {

    function fire(string $event, array $payload)
    {
        try {
            app(BroadcastFactory::class)->send(
                app(MessageFactory::class)->make($event, $payload)
            );
        } catch (Exception $e) {
            Log::error(sprintf('cannot send broadcast event: %s', $e->getMessage()));
        }
    }

}
