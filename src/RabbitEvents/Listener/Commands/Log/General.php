<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Commands\Log;

use Illuminate\Contracts\Container\Container;
use RabbitEvents\Listener\Events\ListenerHandlerExceptionOccurred;

class General extends Writer
{
    public function __construct(
        protected Container $app,
        protected string $defaultLogLevel = 'info',
        protected ?string $channel = null
    ) {
    }

    /**
     * @inheritdoc
     */
    public function log($event): void
    {
        $status = $this->getStatus($event);

        $this->app['log']->channel($this->channel)->log(
            $this->getLogLevel($status),
            sprintf('Handler "%s" %s', $event->handler->getName(), $status),
            $this->getPayload($event, $status),
        );
    }

    protected function getPayload($event, string $status): array
    {
        $payload = [
            'handler' => [
                'name' => $event->handler->getName(),
                'attempts' => $event->handler->attempts(),
                'payload' => $event->handler->payload(),
            ],
            'status' => $status,
        ];

        if ($event instanceof ListenerHandlerExceptionOccurred) {
            $payload['exception'] = $event->exception;
        }

        return $payload;
    }

    protected function getLogLevel(string $status): string
    {
        if (in_array($status, [static::STATUS_EXCEPTION, static::STATUS_FAILED])) {
            return 'error';
        }

        return $this->defaultLogLevel;
    }
}
