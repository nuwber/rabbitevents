<?php

namespace Nuwber\Events;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Events\Dispatcher as BaseDispatcher;

class Dispatcher extends BaseDispatcher
{
    /**
     * Global application's global Rabbitevents middleware stack
     *
     * These middleware are run for every received message.

     * @var array
     */
    protected $middleware = [];

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

    /**
     * Create a class based listener using the IoC container.
     *
     * @param  string  $listener
     * @param  bool  $wildcard
     * @return \Closure|void
     */
    public function createClassListener($listener, $wildcard = false)
    {
        return function ($event, $payload) use ($listener, $wildcard) {
            $callable = $this->createClassCallable($listener);

            if (false === $this->handleMiddleware($callable, $payload)) {
                return;
            }

            if ($wildcard) {
                return call_user_func($callable, $event, $payload);
            }

            return call_user_func_array($callable, $payload);
        };
    }

    /**
     * @param callable $callable
     * @param $payload
     * @return boolean|void
     */
    private function handleMiddleware(callable $callable, $payload)
    {
        if (!is_array($callable)) {
            return;
        }

        $listener = Arr::first($callable);
        if (!is_object($listener)) {
            return;
        }

        if(method_exists($listener, 'middleware')) {
            return $listener->middleware($payload);
        }

        if (isset($listener->middleware) && is_callable($listener->middleware)) {
            return call_user_func($listener->middleware, $payload);
        }
    }
}
