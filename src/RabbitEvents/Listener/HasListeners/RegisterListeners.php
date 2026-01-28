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
        $listeners = [];
        $class = new ReflectionClass($listenerClass);

        // Class Level Attributes
        foreach ($class->getAttributes(Listener::class) as $attribute) {
            /** @var Listener $instance */
            $instance = $attribute->newInstance();
            $listeners[$instance->event][] = [$listenerClass, 'handle'];
        }

        // Method Level Attributes
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(Listener::class) as $attribute) {
                 /** @var Listener $instance */
                $instance = $attribute->newInstance();
                $listeners[$instance->event][] = [$listenerClass, $method->getName()];
            }
        }

        return $listeners;
    }
}
