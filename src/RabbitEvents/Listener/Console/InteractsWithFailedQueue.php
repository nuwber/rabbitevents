<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Console;

use RabbitEvents\Foundation\Connection\ConnectionManager;
use RabbitEvents\Foundation\Consumer;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Listener\Dispatcher;
use RabbitEvents\Listener\QueueName;

trait InteractsWithFailedQueue
{
    protected function getFailedQueueName(): string
    {
        $queue = (string) $this->option('queue');

        if (!$queue) {
            $service = (string) ($this->option('service') ?: ($this->laravel['config']->get('app.name') ?: 'rabbitevents'));
            $events = $this->laravel->bound(Dispatcher::class) ? $this->laravel[Dispatcher::class]->getEvents() : [];
            $queue = QueueName::resolve($service, $events);
        }

        return str_ends_with($queue, '.failed') ? $queue : "{$queue}.failed";
    }

    protected function getContext(): Context
    {
        $connectionName = $this->option('connection') ?: $this->laravel['config']->get('rabbitevents.default');

        if ($this->laravel->bound(ConnectionManager::class)) {
            return $this->laravel[ConnectionManager::class]->context($connectionName);
        }

        return $this->laravel->make(Context::class);
    }

    protected function getConsumer(Context $context, string $queueName): Consumer
    {
        $destination = $context->makeQueue($queueName, [], $context->makeTopic());

        return $context->makeConsumer($destination);
    }
}
