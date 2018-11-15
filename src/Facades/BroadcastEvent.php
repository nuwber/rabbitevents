<?php

namespace Butik\Events\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Butik\Events\Dispatcher
 */
class BroadcastEvent extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'broadcast.events';
    }
}
