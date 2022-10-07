<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Commands\Log;

use Illuminate\Contracts\Container\Container;
use RabbitEvents\Listener\Message\Handler;

class General extends Writer
{
    public function __construct(protected Container $app)
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

    protected function write(Handler $handler, $status): void
    {
        $this->app['log']->log(
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

    protected function getLogLevel($status): string
    {
        if (in_array($status, [static::STATUS_EXCEPTION, $status == static::STATUS_FAILED])) {
            return 'error';
        }

        $connection = $this->app['config']->get('rabbitevents.default', 'rabbitmq');

        return $this->app['config']->get("rabbitevents.connections.$connection.logging.level", 'info');
    }
}
