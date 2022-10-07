<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Support;

use Interop\Amqp\AmqpProducer;
use Interop\Queue\Destination;
use Interop\Queue\Exception;
use Interop\Queue\Exception\InvalidDestinationException;
use Interop\Queue\Exception\InvalidMessageException;
use RabbitEvents\Foundation\Contracts\Transport;
use RabbitEvents\Foundation\Message;

class Sender implements Transport
{
    public function __construct(protected Destination $destination, protected AmqpProducer $producer)
    {
    }

    /**
     * @throws InvalidDestinationException
     * @throws InvalidMessageException
     * @throws Exception
     */
    public function send(Message $message): void
    {
        $this->producer->send($this->destination, $message->amqpMessage());
    }
}
