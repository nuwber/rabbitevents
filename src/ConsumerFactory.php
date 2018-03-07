<?php

namespace Nuwber\Events;

use Interop\Amqp\AmqpContext;
use Interop\Amqp\Impl\AmqpBind;
use Interop\Amqp\Impl\AmqpTopic;
use Interop\Queue\PsrConsumer;
use Interop\Queue\PsrContext;
use Interop\Queue\PsrTopic;

class ConsumerFactory
{
    /**
     * @var AmqpContext
     */
    private $context;
    /**
     * @var AmqpTopic
     */
    private $topic;

    public function __construct(PsrContext $context, PsrTopic $topic)
    {
        $this->context = $context;
        $this->topic = $topic;
    }

    /**
     * @param array $events
     *
     * @return PsrConsumer
     */
    public function make(array $events)
    {
        $consumers = [];
        foreach ($events as $event) {
            $queueName = $this->convertEventNameToQueueName($event);
            $queue = $this->context->createQueue($queueName);
            $this->context->declareQueue($queue);
            $this->bind($queueName, $queue);

            $consumers[] = $this->context->createConsumer($queue);
        }
        return $consumers;
    }

    /**
     * Bind queue to concrete event
     *
     * @param $event
     * @return $this
     */
    protected function bind($event, $queue)
    {
        $this->context->bind(new AmqpBind($this->topic, $queue, $event));

        return $this;
    }

    protected function convertEventNameToQueueName(string $event)
    {
        return str_replace('.*', '.all', $event);
    }
}
