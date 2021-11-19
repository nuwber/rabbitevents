<?php

namespace Nuwber\Events\Event;

use Interop\Amqp\Impl\AmqpMessage;
use Nuwber\Events\Queue\Context;
use JsonException;
use Nuwber\Events\Queue\Message\Factory as MessageFactory;
use Nuwber\Events\Queue\Message\Transport;

class Publisher
{
    /**
     * @var Context
     */
    private $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    /**
     * Publish event to
     *
     * @param ShouldPublish $event
     *
     * @throws JsonException
     */
    public function publish(ShouldPublish $event): void
    {
        $this->transport()->send(
            MessageFactory::make($event->publishEventKey(), $event->toPublish())
        );
    }

    /**
     * @return Transport
     */
    protected function transport(): Transport
    {
        return $this->context->transport();
    }
}
