<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\Listener;
use App\Listeners\WildcardListener;
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
