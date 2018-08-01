<?php

namespace Nuwber\Events;

use Interop\Amqp\AmqpContext;
use Interop\Amqp\Impl\AmqpBind;
use Interop\Amqp\Impl\AmqpQueue;
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
     * @param string $event
     * @param string $serviceName
     * @return PsrConsumer
     */
    public function make(string $event, string $serviceName)
    {
        $eventName = $this->convertQueueNameToEventName($event);
        $queueName = $this->convertEventNameToQueueName($eventName);

        $prefixedEventName = $this->makePrefixedName($eventName, $serviceName);
        $prefixedQueueName = $this->makePrefixedName($queueName, $serviceName);

        $queue = $this->context->createQueue($prefixedQueueName);
        $queue->addFlag(AmqpQueue::FLAG_DURABLE);

        $this->context->declareQueue($queue);

        $this->bind($eventName, $queue);
        $this->bind($prefixedEventName, $queue);

        $consumer = $this->context->createConsumer($queue);

        return $consumer;
    }

    /**
     * Bind queue to concrete event.
     *
     * @param string $event
     * @param AmqpQueue $queue
     * @return $this
     */
    protected function bind(string $event, AmqpQueue $queue)
    {
        $this->context->bind(new AmqpBind($this->topic, $queue, $event));

        return $this;
    }

    /**
     * Convert event name to queue name.
     *
     * @param string $event
     * @return null|string
     */
    protected function convertEventNameToQueueName(string $event)
    {
        return preg_replace('/\.\*$/', '.all', $event);
    }

    /**
     * Convert queue name to event name.
     *
     * @param string $queueName
     * @return null|string
     */
    protected function convertQueueNameToEventName(string $queueName)
    {
        return preg_replace('/\.all$/', '.*', $queueName);
    }

    /**
     * Make queue or event name prefixed.
     *
     * @param string $name
     * @param string $serviceName
     * @return string
     */
    protected function makePrefixedName(string $name, string $serviceName)
    {
        return $serviceName . '-' . $name;
    }
}
