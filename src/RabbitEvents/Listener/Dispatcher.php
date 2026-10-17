<?php

declare(strict_types=1);

namespace RabbitEvents\Listener;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Events\Dispatcher as BaseDispatcher;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Dispatcher extends BaseDispatcher
{
    public function __construct(?ContainerContract $container = null)
    {
        parent::__construct($container ?? new Container());
    }

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
        foreach ((array) $events as $event) {
            if (str_contains($event, '*')) {
                $this->setupWildcardListen($event, $listener);
            } else {
                // Check for duplicates before adding
                if (!$this->isDuplicateListener($event, $listener)) {
                    $this->listeners[$event][] = $listener;
                }
            }
        }
    }

    /**
     * Check if a listener is already registered for an event.
     *
     * @param string $event
     * @param mixed $listener
     * @return bool
     */
    protected function isDuplicateListener(string $event, $listener): bool
    {
        if (!isset($this->listeners[$event])) {
            return false;
        }

        $normalizedListener = $this->normalizeListenerForComparison($listener);

        foreach ($this->listeners[$event] as $existingListener) {
            if ($normalizedListener === $this->normalizeListenerForComparison($existingListener)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize a listener to a comparable format.
     *
     * @param mixed $listener
     * @return string
     */
    protected function normalizeListenerForComparison($listener): string
    {
        if (is_array($listener)) {
            return $listener[0] . '@' . ($listener[1] ?? 'handle');
        }

        if (is_string($listener)) {
            // Strip '::class' suffix if present 
            if (str_contains($listener, '::class')) {
                $listener = str_replace('::class', '', $listener);
            }

            if (str_contains($listener, '@')) {
                return $listener;
            }

            return $listener . '@handle';
        }

        if ($listener instanceof Closure) {
            return spl_object_hash($listener);
        }

        return serialize($listener);
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

            if (empty($throughMiddleware)) {
                return parent::makeListener($listener, $wildcard)($event, $payload);
            }

            $pipes = array_map(
                fn ($middleware) => $this->wrapMiddlewareIntoPipelineStage($middleware, $event, $wildcard),
                $throughMiddleware
            );

            return (new Pipeline($this->container))
                ->send($payload)
                ->through($pipes)
                ->then(function ($payload) use ($listener, $wildcard, $event) {
                    return parent::makeListener($listener, $wildcard)($event, $payload);
                });
        };
    }

    protected static array $middlewareExpectsNextCache = [];

    protected function wrapMiddlewareIntoPipelineStage(callable $middleware, string $event, bool $wildcard): Closure
    {
        $expectsNext = $this->middlewareExpectsNext($middleware);

        return function ($passable, $next) use ($middleware, $event, $wildcard, $expectsNext) {
            if ($expectsNext) {
                return $wildcard
                    ? ($middleware)($event, $passable, $next)
                    : ($middleware)($passable, $next);
            }

            $result = $wildcard
                ? ($middleware)($event, ...array_values($passable))
                : ($middleware)(...$passable);

            if (false === $result) {
                return null;
            }

            return $next($passable);
        };
    }

    protected function middlewareExpectsNext(callable $middleware): bool
    {
        $key = is_array($middleware)
            ? (is_object($middleware[0]) ? get_class($middleware[0]) : $middleware[0]) . '@' . $middleware[1]
            : ($middleware instanceof \Closure ? spl_object_hash($middleware) : (string) $middleware);

        if (isset(static::$middlewareExpectsNextCache[$key])) {
            return static::$middlewareExpectsNextCache[$key];
        }

        $reflector = is_array($middleware)
            ? new \ReflectionMethod($middleware[0], $middleware[1])
            : new \ReflectionFunction(\Closure::fromCallable($middleware));

        $parameters = $reflector->getParameters();
        $lastParam = !empty($parameters) ? end($parameters) : null;

        $expectsNext = false;
        if ($lastParam) {
            if ($lastParam->getName() === 'next') {
                $expectsNext = true;
            } elseif ($lastParam->hasType()) {
                $type = $lastParam->getType();
                $types = $type instanceof \ReflectionUnionType ? $type->getTypes() : [$type];
                foreach ($types as $namedType) {
                    if ($namedType instanceof \ReflectionNamedType) {
                        $typeName = ltrim($namedType->getName(), '\\');
                        if (in_array($typeName, ['Closure', 'callable'], true)) {
                            $expectsNext = true;
                            break;
                        }
                    }
                }
            }
        }

        return static::$middlewareExpectsNextCache[$key] = $expectsNext;
    }

    protected function makeListenerInstance($listener)
    {
        if (is_array($listener)) {
            return is_object($listener[0]) ? $listener[0] : $this->makeListenerInstance($listener[0]);
        }

        if (is_string($listener)) {
            [$class,] = Str::parseCallback($listener);

            return $this->container->instance($class, $this->container->make($class));
        }

        if (is_object($listener)) {
            return $listener;
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
            foreach ((array) $instance->middleware as $middleware) {
                $result[] = $this->createMiddlewareCallable($middleware);
            }
        }

        if (method_exists($instance, 'middleware')) {
            $result[] = [$instance, 'middleware'];
        }

        return $result;
    }

    protected function createMiddlewareCallable($mixed): callable
    {
        if (is_object($mixed) && method_exists($mixed, 'middleware')) {
            return [$mixed, 'middleware'];
        }

        if (is_object($mixed) && method_exists($mixed, 'handle')) {
            return [$mixed, 'handle'];
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
