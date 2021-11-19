<?php

namespace Nuwber\Events;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Events\Dispatcher as BaseDispatcher;

class Dispatcher extends BaseDispatcher
{
    /**
     * @return array
     */
    public function getEvents(): array
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
    public function listen($events, $listener = null): void
    {
        foreach ((array)$events as $event) {
            if (Str::contains($event, '*')) {
                $this->setupWildcardListen($event, $listener);
            } else {
                $this->listeners[$event][$this->getListenerClass($listener)][] = $this->makeListener($listener);
            }
        }
    }

    /*
     * @inheritdoc
     */
    public function makeListener($listener, $wildcard = false): \Closure
    {
        return function ($event, $payload) use ($listener, $wildcard) {
            $throughMiddleware = $this->extractMiddleware($listener);

            if (!$wildcard && Arr::isAssoc($payload)) {
                $payload = [$payload];
            }

            foreach ($throughMiddleware as $middleware) {
                $result = $wildcard
                    ? call_user_func($middleware, $event, ...array_values($payload))
                    : call_user_func_array($middleware, $payload);

                if (false === $result) {
                    return null;
                }
            }

            return parent::makeListener($listener, $wildcard)($event, $payload);
        };
    }

    /**
     * Setup a wildcard listener callback.
     *
     * @param string $event
     * @param mixed $listener
     * @return void
     */
    protected function setupWildcardListen($event, $listener): void
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

    protected function makeListenerInstance($listener)
    {
        if (is_string($listener)) {
            list($class,) = Str::parseCallback($listener);

            return $this->container->instance($class, $this->container->make($class));
        }

        if (is_object($listener)) {
            return $listener;
        }

        return null;
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
