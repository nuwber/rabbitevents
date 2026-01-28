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
            if (str_contains($event, '*')) {
                $this->setupWildcardListen($event, $listener);
            } else {
                $this->listeners[$event][] = $listener;
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function makeListener($listener, $wildcard = false): Closure
    {
        if ($listener instanceof Closure) {
            return $listener;
        }

        return function ($event, $payload) use ($listener, $wildcard) {
            $throughMiddleware = $this->extractMiddleware($listener);

            if (!$wildcard && (!is_array($payload) || Arr::isAssoc($payload))) {
                $payload = [$payload];
            }

            foreach ($throughMiddleware as $middleware) {
                $result = $wildcard
                    ? ($middleware)($event, ...array_values($payload))
                    : ($middleware)(...$payload);

                if (false === $result) {
                    return null;
                }
            }

            return parent::makeListener($listener, $wildcard)($event, $payload);
        };
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
