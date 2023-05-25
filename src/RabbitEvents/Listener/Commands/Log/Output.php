<?php

declare(strict_types=1);

namespace RabbitEvents\Listener\Commands\Log;

use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Carbon;
use RabbitEvents\Listener\Message\Handler;

class Output extends Writer
{
    public function __construct(protected Container $app, protected OutputStyle $output)
    {
    }

    /**
     * @inheritdoc
     */
    public function log($event): void
    {
        $status = $this->getStatus($event);

        $this->writeStatus($event->handler, $status, $this->getType($status));
        if (isset($event->exception)) {
            $this->output->writeln('Exception message: ' . $event->exception->getMessage());
        }
    }

    /**
     * Format the status output for the queue worker.
     *
     * @param  Handler $handler
     * @param  string $status
     * @param  string $type
     * @return void
     */
    protected function writeStatus(Handler $handler, string $status, string $type): void
    {
        $this->output->writeln(sprintf(
            "<{$type}>[%s] %s</{$type}> %s",
            Carbon::now()->format('Y-m-d H:i:s'),
            str_pad("{$status}:", 11),
            $handler->getName()
        ));
    }

    protected function getType($status): string
    {
        return match ($status) {
            self::STATUS_PROCESSED => 'info',
            self::STATUS_FAILED => 'error',
            default => 'comment',
        };
    }
}
