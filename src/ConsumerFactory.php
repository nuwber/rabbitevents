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
     * @param string $event
     * @param string $serviceName
     * @return PsrConsumer
     */
    public function make(string $event, string $serviceName)
    {
        $event = $this->convertQueueNameToEventName($event);
        $queueName = $this->convertEventNameToQueueName($event);

        $queueName = $this->makeQueueName($queueName, $serviceName);

        $queue = $this->context->createQueue($queueName);
        $this->context->declareQueue($queue);

        $this->bind($event, $queue);

        $consumer = $this->context->createConsumer($queue);

        return $consumer;
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

    /**
     * Convert event name to queue name
     *
     * @param string $event
     * @return null|string
     */
    protected function convertEventNameToQueueName(string $event)
    {
        return preg_replace('/\.\*$/', '.all', $event);
    }

    /**
     * Convert queue name to event name
     *
     * @param string $queue
     * @return null|string
     */
    protected function convertQueueNameToEventName(string $queue)
    {
        return preg_replace('/\.all$/', '.*', $queue);
    }

    protected function makeQueueName($queueName, $serviceName) {
        return $serviceName.'-'.$queueName;
    }
}
