<?php

declare(strict_types=1);

namespace RabbitEvents\Foundation\Support;

use Illuminate\Support\InteractsWithTime;
use Interop\Amqp\AmqpProducer;
use Interop\Queue\Exception\DeliveryDelayNotSupportedException;
use RabbitEvents\Foundation\Context;
use RabbitEvents\Foundation\Contracts\Transport;
use RabbitEvents\Foundation\Message;

class Sender implements Transport
{
    use InteractsWithTime;

    /**
     * @var AmqpProducer
     */
    private $producer;

    public function __construct(private Context $context)
    {
    }

    /**
     * @param Message $message
     * @param int $delay
     */
    public function send(Message $message, int $delay = 0): void
    {
        $this->setDelay($delay);

        $this->producer()->send($this->context->destination(), $message->amqpMessage());
    }

    private function setDelay(int $delay = 0): void
    {
        try {
            $this->producer()->setDeliveryDelay($this->secondsUntil($delay) * 1000);
        } catch (DeliveryDelayNotSupportedException $e) {
        }
    }

    private function producer(): AmqpProducer
    {
        if (!$this->producer) {
            $this->producer = $this->context->createProducer();
        }

        return $this->producer;
    }
}
