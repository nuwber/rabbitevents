<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\HasListeners;

use RabbitEvents\Listener\Attributes\Listener;
use ReflectionClass;
use ReflectionMethod;

trait RegisterListeners
{
    /**
     * The list of listener classes that should be auto-discovered.
     *
     * @var array
     */
    protected array $listenerClasses = [];

    /**
     * Get the events and handlers from the attribute listeners.
     * 
     * @return array
     */
    public function getEventsFromAttributes(): array
    {
        $listeners = [];

        foreach ($this->listenerClasses as $listenerClass) {
            $listeners = array_merge_recursive($listeners, $this->resolveListenerClass($listenerClass));
        }

        return $listeners;
    }

    protected function resolveListenerClass(string $listenerClass): array
    {
        return ListenerDiscoverer::eventsFromClass($listenerClass);
    }

    protected function deduplicateListeners(array $listeners): array
    {
        $deduplicated = [];

        foreach ($listeners as $event => $eventListeners) {
            $seen = [];
            foreach ($eventListeners as $listener) {
                $key = $this->normalizeListener($listener);

                if (!in_array($key, $seen)) {
                    $seen[] = $key;
                    $deduplicated[$event][] = $listener;
                }
            }
        }

        return $deduplicated;
    }

    protected function normalizeListener($listener): string
    {
        if (is_array($listener)) {
            return $listener[0] . '@' . ($listener[1] ?? 'handle');
        }

        if (is_string($listener)) {
            if (str_contains($listener, '@')) {
                return $listener;
            }

            return $listener . '@handle';
        }

        return serialize($listener);
    }
}
