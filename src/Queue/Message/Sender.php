<?php

namespace Nuwber\Events\Queue\Message;

use Illuminate\Support\InteractsWithTime;
use Interop\Queue\Destination;
use Interop\Queue\Message;
use Interop\Queue\Producer;
use Interop\Queue\Exception\DeliveryDelayNotSupportedException;

class Sender implements Transport
{
    use InteractsWithTime;

    /**
     * @var Destination
     */
    private $topic;

    /**
     * @var Producer
     */
    private $producer;

    public function __construct(Producer $producer, Destination $topic)
    {
        $this->producer = $producer;
        $this->topic = $topic;
    }

    /**
     * @param Message $message
     * @param int $delay
     * @throws \Interop\Queue\Exception
     * @throws \Interop\Queue\Exception\InvalidDestinationException
     * @throws \Interop\Queue\Exception\InvalidMessageException
     */
    public function send(Message $message, int $delay = 0): void
    {
        try {
            $this->producer->setDeliveryDelay($this->secondsUntil($delay) * 1000);
        } catch (DeliveryDelayNotSupportedException $e) {
        }

        $this->producer->send($this->topic, $message);
    }
}
