<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Amqp;

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
        $queue = $this->context->createQueue($queueName);

        if ($this->connection->getConfig('durable', true)) {
            $queue->addFlag(AmqpDestination::FLAG_DURABLE);
        }

        (new DeadLetterConfigurator($this->context, $this->connection))->configure($queue, $queueName);

        $this->connection->getDeduplicationStrategy()->prepareQueue($queue);

        try {
            $this->context->declareQueue($queue);

            return $queue;
        } catch (\Throwable $e) {
            if ($this->isPreconditionFailedException($e)) {
                try {
                    if (class_exists(Log::class)) {
                        Log::warning(
                            "RabbitEvents: Queue [{$queueName}] has incompatible arguments (likely created by RabbitEvents 9.x). Falling back to application-level dead lettering without modifying queue arguments: " . $e->getMessage()
                        );
                    }
                } catch (\Throwable) {
                    // Log facade not initialized
                }

                // RabbitMQ closes the channel on 406 PRECONDITION_FAILED.
                // We recreate a fresh context with an open channel and declare the queue without conflicting arguments.
                $freshContext = method_exists($this->connection, 'recreateContext')
                    ? $this->connection->recreateContext()
                    : $this->connection->createContext();
                $fallbackQueue = $freshContext->createQueue($queueName);

                if ($this->connection->getConfig('durable', true)) {
                    $fallbackQueue->addFlag(AmqpDestination::FLAG_DURABLE);
                }

                $freshContext->declareQueue($fallbackQueue);

                return $fallbackQueue;
            }

            throw $e;
        }
    }

    protected function isPreconditionFailedException(\Throwable $e): bool
    {
        return $e->getCode() === 406
            || str_contains($e->getMessage(), 'PRECONDITION_FAILED')
            || str_contains($e->getMessage(), '406');
    }
}
