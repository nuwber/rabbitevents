<?php
namespace app\Providers;

use RabbitEvents\Listener\ListenerServiceProvider;

class RabbitEventsServiceProvider extends ListenerServiceProvider
{
    protected array $listen = [
        'some.event' => [
            Listener::class
        ],
        'something.*' => [
            WildcardListener::class
        ],
    ];
}
