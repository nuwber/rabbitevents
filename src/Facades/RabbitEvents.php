<?php

namespace Nuwber\Events\Facades;

use Illuminate\Support\Facades\Facade;
use Nuwber\Events\Dispatcher;

/**
 * @see Dispatcher
 */
class RabbitEvents extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return Dispatcher::class;
    }
}
