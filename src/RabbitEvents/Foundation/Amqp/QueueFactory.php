<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Amqp;

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpDestination;
use Interop\Amqp\AmqpQueue;

class QueueFactory
{
    public function __construct(private readonly AmqpContext $context, private readonly Connection $connection)
    {
    }

    /**
     * @param string $queueName
     * @return AmqpQueue
     */
    public function makeAndDeclare(string $queueName): AmqpQueue
    {
        $queue = $this->createQueue($this->context, $queueName);

        (new DeadLetterConfigurator($this->context, $this->connection))->configure($queue, $queueName);

        $this->connection->getDeduplicationStrategy()->prepareQueue($queue);

        try {
            $this->context->declareQueue($queue);

            return $queue;
        } catch (\Throwable $e) {
            if (!$this->isPreconditionFailedException($e)) {
                throw $e;
            }

            if (Facade::getFacadeApplication()) {
                Log::warning(
                    "RabbitEvents: Queue [{$queueName}] has incompatible arguments (likely created by RabbitEvents 9.x). Falling back to application-level dead lettering without modifying queue arguments: " . $e->getMessage()
                );
            }

            // RabbitMQ closes the channel on 406 PRECONDITION_FAILED.
            // Recreate a fresh context with an open channel and declare the queue without conflicting arguments.
            $freshContext = $this->connection->recreateContext();
            $fallbackQueue = $this->createQueue($freshContext, $queueName);

            $freshContext->declareQueue($fallbackQueue);

            return $fallbackQueue;
        }
    }

    protected function createQueue(AmqpContext $context, string $queueName): AmqpQueue
    {
        $queue = $context->createQueue($queueName);

        if ($this->connection->getConfig('durable', true)) {
            $queue->addFlag(AmqpDestination::FLAG_DURABLE);
        }

        return $queue;
    }

    protected function isPreconditionFailedException(\Throwable $e): bool
    {
        return $e->getCode() === 406
            || str_contains($e->getMessage(), 'PRECONDITION_FAILED')
            || str_contains($e->getMessage(), '406');
    }
}
