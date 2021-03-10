<?php

namespace Nuwber\Events\Facades;

use Illuminate\Support\Facades\Facade;
use Nuwber\Events\Dispatcher;

/**
 * @see Dispatcher
 * @method static array getListeners(string $event)
 * @method static array getEvents()
 * @method static void listen(string|array $event, mixed $listener = null)
 */
class RabbitEvents extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return Dispatcher::class;
    }
}
