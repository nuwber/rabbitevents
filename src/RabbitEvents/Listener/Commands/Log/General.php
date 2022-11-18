<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Commands\Log;

use Illuminate\Contracts\Container\Container;
use RabbitEvents\Listener\Message\Handler;

class General extends Writer
{
    public function __construct(protected Container $app, protected string $defaultLogLevel = 'info', protected ?string $channel = null)
    {
    }

    /**
     * @inheritdoc
     */
    public function log($event): void
    {
        $status = $this->getStatus($event);

        $this->write($event->handler, $status);
    }

    protected function write(Handler $handler, string $status): void
    {
        $this->app['log']->channel($this->channel)->log(
            $this->getLogLevel($status),
            sprintf('Handler "%s" %s', $handler->getName(), $status),
            [
                'handler' => [
                    'name' => $handler->getName(),
                    'attempts' => $handler->attempts(),
                    'payload' => $handler->payload(),
                ],
                'status' => $status,
            ]
        );
    }

    protected function getLogLevel(string $status): string
    {
        if (in_array($status, [static::STATUS_EXCEPTION, static::STATUS_FAILED])) {
            return 'error';
        }

        return $this->defaultLogLevel;
    }
}
