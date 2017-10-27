<?php

namespace Nuwber\Events;

use Illuminate\Support\Str;
use Illuminate\Events\Dispatcher as BaseDispatcher;

class Dispatcher extends BaseDispatcher
{
    public function getEvents()
    {
        return array_merge(array_keys($this->listeners), array_keys($this->wildcards));
    }

    /**
     * Register an event listener with the dispatcher.
     *
     * @param  string|array $events
     * @param  mixed $listener
     * @return void
     */
    public function listen($events, $listener)
    {
        foreach ((array)$events as $event) {
            if (Str::contains($event, '*')) {
                $this->setupWildcardListen($event, $listener);
            } else {
                $this->listeners[$event][$this->getListenerClass($listener)][] = $this->makeListener($listener);
            }
        }
    }

    /**
     * Setup a wildcard listener callback.
     *
     * @param  string $event
     * @param  mixed $listener
     * @return void
     */
    protected function setupWildcardListen($event, $listener)
    {
        $this->wildcards[$event][$this->getListenerClass($listener)][] = $this->makeListener($listener, true);
    }

    protected function getListenerClass($listener)
    {
        if ($listener instanceof \Closure) {
            return \Closure::class;
        }

        return $listener;
    }
}
