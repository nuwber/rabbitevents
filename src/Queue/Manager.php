<?php

namespace Nuwber\Events\Queue;

use Illuminate\Support\InteractsWithTime;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpMessage;
use Nuwber\Events\Queue\Message\Transport;

class Manager
{
    use InteractsWithTime;

    /**
     * @var AmqpConsumer
     */
    private $consumer;

    /**
     * @var Transport
     */
    private $transport;

    public function __construct(AmqpConsumer $consumer, Transport $transport)
    {
        $this->consumer = $consumer;
        $this->transport = $transport;
    }

    /**
     * @param int $timeout
     * @return AmqpMessage|null
     */
    public function nextMessage(int $timeout = 0): ?AmqpMessage
    {
        return $this->consumer->receive($timeout);
    }

    public function getEvent(): string
    {
        return $this->consumer->getQueue()->getQueueName();
    }

    public function send(AmqpMessage $message, int $delay = 0): void
    {
        $this->transport->send($message, $delay);
    }

    public function acknowledge(AmqpMessage $amqpMessage): void
    {
        $this->consumer->acknowledge($amqpMessage);
    }

    /**
     * @param AmqpMessage $message
     * @param int $attempts
     * @param int $delay
     */
    public function release(AmqpMessage $message, int $attempts = 1, int $delay = 0): void
    {
        $requeueMessage = clone $message;
        $requeueMessage->setProperty('x-attempts', ++$attempts);

        $this->send($requeueMessage, (int) $delay);
    }
}
