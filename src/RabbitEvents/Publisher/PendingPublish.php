<?php

namespace RabbitEvents\Publisher;

use Exception;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;

class PendingPublish
{
    public function __construct(protected ShouldPublish $event)
    {

    }

    /**
     * @throws Exception
     */
    public function afterCommit(bool $option = true): static
    {
        if (!property_exists($this->event, 'afterCommit')) {
            throw new Exception('The event class should use PublishableAfterCommit trait');
        }
        $this->event->afterCommit = $option;
        return $this;
    }

    /**
     * @throws Exception
     */
    public function priority(?int $priority): static
    {
        if (!property_exists($this->event, 'priority')) {
            throw new Exception('The event class should use HasPriority trait');
        }
        $this->event->priority = $priority;
        return $this;
    }

    /**
     * @throws BindingResolutionException
     */
    public function __destruct()
    {
        Container::getInstance()
            ->make(Publisher::class)
            ->publish($this->event);
    }
}