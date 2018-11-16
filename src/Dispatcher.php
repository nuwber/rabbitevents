<?php

namespace Butik\Events;

use Illuminate\Events\Dispatcher as BaseDispatcher;
use Butik\Events\Contracts\ExternalEvent as ExternalEventContract;

/**
 * @author Sergey Kvartnikov <s.kvartnikov@butik.ru>
 *
 * Created at 15.11.2018
 */
class Dispatcher extends BaseDispatcher
{
    /**
     * @param object|string $event
     * @param array         $payload
     * @param bool          $halt
     *
     * @return mixed
     */
    public function dispatch($event, $payload = [], $halt = false)
    {
        if (is_object($event) && $event instanceof ExternalEventContract) {
            fire($event->getExternalEventName(), $event->getExternalPayload());
        }

        return parent::dispatch($event, $payload, $halt);
    }
}
