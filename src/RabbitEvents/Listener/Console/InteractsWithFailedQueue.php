<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Console;

use Illuminate\Support\Carbon;
use RabbitEvents\Foundation\Connection\ConnectionManager;
use RabbitEvents\Foundation\Consumer;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Foundation\Message;
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

    protected function eachFailedMessage(callable $callback, int $timeout = 500): void
    {
        $context = $this->getContext();
        $consumer = $this->getConsumer($context, $this->getFailedQueueName());
        $seen = [];

        while ($message = $consumer->nextMessage($timeout)) {
            $id = $message->id();
            if ($id && isset($seen[$id])) {
                $consumer->reject($message, true);
                break;
            }

            if ($id) {
                $seen[$id] = true;
            }

            $continue = $callback($message, $consumer, $context);
            if ($continue === false) {
                break;
            }
        }
    }

    protected function extractFailedAt(Message $message): string
    {
        if ($failedAt = $message->header('x-failed-at')) {
            return Carbon::createFromTimestamp((int) $failedAt)->toDateTimeString();
        }

        if ($deaths = $message->header('x-death')) {
            $time = $deaths[0]['time'] ?? null;
            if ($time instanceof \DateTimeInterface) {
                return Carbon::instance($time)->toDateTimeString();
            }
            if (is_numeric($time)) {
                return Carbon::createFromTimestamp((int) $time)->toDateTimeString();
            }
        }

        return 'Unknown';
    }

    protected function extractEvent(Message $message): string
    {
        if ($originalKey = $message->header('x-original-routing-key')) {
            return $originalKey;
        }

        if ($deaths = $message->header('x-death')) {
            $routingKeys = $deaths[0]['routing-keys'] ?? [];
            if (!empty($routingKeys[0])) {
                return $routingKeys[0];
            }
        }

        return $message->event();
    }

    protected function extractException(Message $message): string
    {
        return $message->header('x-exception-message')
            ?? $message->header('x-exception-class')
            ?? 'N/A';
    }

    protected function getOriginalQueueName(Message $message): string
    {
        if ($deaths = $message->header('x-death')) {
            if (!empty($deaths[0]['queue'])) {
                return $deaths[0]['queue'];
            }
        }

        $failedQueue = $this->getFailedQueueName();

        return str_ends_with($failedQueue, '.failed')
            ? substr($failedQueue, 0, -7)
            : $failedQueue;
    }
}
