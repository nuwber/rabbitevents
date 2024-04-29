<?php

declare(strict_types=1);

namespace RabbitEvents\Listener;

use Closure;
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
                $this->listeners[$event][] = $listener;
            }
        }
    }

    /**
     * Get all of the listeners for a given event name.
     * Make it working with Laravel 8.x and 9.x
     *
     * TODO: remove this function when 9.x become minimal supported version
     *
     * @param  string  $eventName
     * @return array
     */
    public function getListeners($eventName): array
    {
        return array_merge(
            $this->prepareListeners($eventName),
            $this->wildcardsCache[$eventName] ?? $this->getWildcardListeners($eventName)
        );
    }

    /**
     * Get the wildcard listeners for the event.
     *
     * @param  string  $eventName
     * @return array
     */
    protected function getWildcardListeners($eventName): array
    {
        $wildcards = [];

        foreach ($this->wildcards as $key => $listeners) {
            if (Str::is($key, $eventName)) {
                foreach ($listeners as $listener) {
                    $wildcards[] = [$this->getListenerClass($listener), $this->makeListener($listener, true)];
                }
            }
        }

        return $this->wildcardsCache[$eventName] = $wildcards;
    }

    /**
     * Prepare the listeners for a given event.
     *
     * @param  string  $eventName
     * @return Closure[]
     */
    protected function prepareListeners(string $eventName): array
    {
        $listeners = [];

        foreach ($this->listeners[$eventName] ?? [] as $listener) {
            $listeners[] = [$this->getListenerClass($listener), $this->makeListener($listener)];
        }

        return $listeners;
    }

    /*
     * @inheritdoc
     */
    public function makeListener($listener, $wildcard = false): Closure
    {
        if ($listener instanceof Closure) {
            return $listener;
        }

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

    protected function getListenerClass($listener): string
    {
        if (is_string($listener)) {
            return $listener;
        }

        if ($listener instanceof Closure) {
            return Closure::class;
        }

        if (is_object($listener)) {
            return get_class($listener);
        }

        return 'Unknown Class';
    }

    protected function makeListenerInstance($listener)
    {
        if (is_string($listener)) {
            [$class,] = Str::parseCallback($listener);

            return $this->container->instance($class, $this->container->make($class));
        }

        if (is_object($listener)) {
            return is_callable($listener) ? $listener : null;
        }

        return null;
    }

    /**
     * @param $listener
     * @return ?array
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

    protected function createMiddlewareCallable($mixed): callable
    {
        if (is_object($mixed) && method_exists($mixed, 'middleware')) {
            return [$mixed, 'middleware'];
        }

        if (is_callable($mixed)) {
            return $mixed;
        }

        if (is_string($mixed)) {
            return $this->createClassCallable($mixed);
        }

        throw new \RuntimeException('Invalid middleware definition');
    }

    protected function handlerShouldBeQueued($class): bool
    {
        return false;
    }
}
