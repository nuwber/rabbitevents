<?php
namespace app\Providers;

class RabbitEventsServiceProvider extends \Nuwber\Events\RabbitEventsServiceProvider
{
    protected $listen = [
        'some.event' => [
            Listener::class
        ],
        'something.*' => [
            WildcardListener::class
        ],
    ];
}
