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
     * @param string|array $events
     * @param mixed $listener
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
     * @param string $event
     * @param mixed $listener
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
     * @param \Closure|string $listener
     * @param bool $wildcard
     * @return \Closure
     */
    public function makeListener($listener, $wildcard = false)
    {
        return function ($event, $payload) use ($listener, $wildcard) {
            $callback = parent::makeListener($listener, $wildcard);

            $throughMiddleware = $this->extractMiddleware($listener);

            foreach ($throughMiddleware as $middleware) {
                $result = $wildcard
                    ? call_user_func($middleware, $event, ...array_values($payload))
                    : call_user_func_array($middleware, $payload);

                if (false === $result) {
                    return null;
                }
            }

            return $callback($event, $payload);
        };
    }

    /**
     * @param $listener
     * @return array
     */
    protected function extractMiddleware($listener): ?array
    {
        $result = [];

        if (!$instance = $this->makeListenerInstance($listener)) {
            return $result;
        }

        if (isset($instance->middleware)) {
            foreach ((array)$instance->middleware as $middleware) {
                $result[] = $this->createMiddlewareCallable($middleware);
            }
        }

        if (method_exists($instance, 'middleware')) {
            $result[] = $this->createMiddlewareCallable($instance);
        }

        return $result;
    }

    public function makeListenerInstance($listener)
    {
        if (is_string($listener) ) {
            list($class,) = Str::parseCallback($listener);

            return $this->container->instance($class, $this->container->make($class));
        }

        if (is_object($listener)) {
            return $listener;
        }

        return null;
    }

    protected function createMiddlewareCallable($mixed)
    {
        if (is_callable($mixed)) {
            return $mixed;
        }

        if (is_string($mixed)) {
            return $this->createClassCallable($mixed);
        }

        if (is_object($mixed)) {
            return [$mixed, 'middleware'];
        }
    }

    protected function handlerShouldBeQueued($class)
    {
        return false;
    }
}
